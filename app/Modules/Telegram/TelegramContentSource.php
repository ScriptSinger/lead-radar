<?php

namespace App\Modules\Telegram;

use App\Models\TelegramChannel;
use App\Models\TelegramPost;
use App\Services\Telegram\TelegramMtprotoClient;
use App\Support\TelegramChannelUrl;
use Carbon\CarbonImmutable;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Read-only MTProto source for posts in public broadcast channels. */
class TelegramContentSource
{
    public function __construct(private readonly TelegramMtprotoClient $mtproto) {}

    /**
     * Resolve a public `t.me` channel into its stable MTProto peer details.
     *
     * @return array{url: string, username: string, telegram_channel_id: int, access_hash: string, name: string}
     */
    public function resolveChannel(string $value): array
    {
        $username = TelegramChannelUrl::username($value);
        $client = $this->authorizedClient();

        try {
            $info = $client->getInfo('@'.$username);
        } catch (Throwable $e) {
            throw new TelegramContentSourceException("Unable to resolve Telegram channel @{$username}.", previous: $e);
        }

        $chat = is_array($info) && is_array($info['Chat'] ?? null) ? $info['Chat'] : null;
        if ($chat === null || ($chat['_'] ?? null) !== 'channel' || ($chat['megagroup'] ?? false)) {
            throw new TelegramContentSourceException("@{$username} is not a public broadcast channel.");
        }

        $channelId = (int) ($chat['id'] ?? 0);
        $accessHash = (string) ($chat['access_hash'] ?? '');
        if ($channelId === 0 || $accessHash === '') {
            throw new TelegramContentSourceException("Telegram did not return complete peer data for @{$username}.");
        }

        return [
            'url' => 'https://t.me/'.$username,
            'username' => strtolower((string) ($chat['username'] ?? $username)),
            'telegram_channel_id' => $channelId,
            'access_hash' => $accessHash,
            'name' => trim((string) ($chat['title'] ?? $username)),
        ];
    }

    /**
     * Resolve a channel and return its latest normalized posts.
     *
     * @return array{channel: array{url: string, username: string, telegram_channel_id: int, access_hash: string, name: string}, posts: list<array<string, mixed>>}
     */
    public function fetchChannel(string $value, int $limit): array
    {
        $channel = $this->resolveChannel($value);

        return [
            'channel' => $channel,
            'posts' => $this->fetchPostsByUsername($channel['username'], $limit),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function fetchPosts(TelegramChannel $channel, int $limit): array
    {
        $username = $channel->username ?: TelegramChannelUrl::username($channel->url);

        return $this->fetchPostsByUsername($username, $limit);
    }

    /** @return list<array<string,mixed>> */
    public function fetchComments(TelegramPost $post, int $limit = 100): array
    {
        $client = $this->authorizedClient();
        $username = $post->channel->username;
        if (! $username) {
            return [];
        }
        try {
            $discussion = $client->messages->getDiscussionMessage(peer: '@'.$username, msg_id: $post->telegram_message_id);
            $root = $discussion['messages'][0] ?? null;
            $peer = $discussion['chats'][0] ?? null;
            if (! is_array($root) || ! is_array($peer)) {
                return [];
            }
            $replies = $client->messages->getReplies(peer: $peer, msg_id: (int) $root['id'], limit: max(1, min(100, $limit)));
        } catch (Throwable $e) {
            // Comments are optional per post. A channel may have discussions
            // disabled, or a particular post may not have a linked thread.
            Log::info('telegram.comments.unavailable', [
                'post_id' => $post->id,
                'telegram_message_id' => $post->telegram_message_id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return array_values(array_filter(array_map(function ($m) {
            if (! is_array($m) || ($m['_'] ?? null) !== 'message' || empty($m['id'])) {
                return null;
            }
            $from = $m['from_id'] ?? null;

            return ['telegram_message_id' => (int) $m['id'], 'parent_telegram_message_id' => isset($m['reply_to']['reply_to_msg_id']) ? (int) $m['reply_to']['reply_to_msg_id'] : null, 'text' => (string) ($m['message'] ?? ''), 'author_telegram_id' => is_array($from) && isset($from['user_id']) ? (int) $from['user_id'] : null, 'posted_at' => CarbonImmutable::createFromTimestampUTC((int) $m['date'])];
        }, $replies['messages'] ?? [])));
    }

    /** @return list<array<string, mixed>> */
    private function fetchPostsByUsername(string $username, int $limit): array
    {
        $client = $this->authorizedClient();
        $limit = max(1, min(100, $limit));

        try {
            $history = $client->messages->getHistory(peer: '@'.$username, limit: $limit);
        } catch (Throwable $e) {
            throw new TelegramContentSourceException("Unable to load posts from @{$username}.", previous: $e);
        }

        $messages = is_array($history['messages'] ?? null) ? $history['messages'] : [];
        $posts = [];
        foreach ($messages as $message) {
            if (! is_array($message) || ($message['_'] ?? null) !== 'message') {
                continue;
            }

            $post = $this->normalizeMessage($message, $username);
            if ($post !== null) {
                $posts[] = $post;
            }
        }

        return $posts;
    }

    /** @return array<string, mixed>|null */
    /** @return array<string, mixed>|null */
    public function normalizeMessage(array $message, string $username): ?array
    {
        $messageId = (int) ($message['id'] ?? 0);
        $timestamp = (int) ($message['date'] ?? 0);
        if ($messageId <= 0 || $timestamp <= 0) {
            return null;
        }

        $fromId = $message['from_id'] ?? null;
        $authorId = is_array($fromId) && isset($fromId['user_id'])
            ? (int) $fromId['user_id']
            : (is_int($fromId) ? $fromId : null);
        $media = $message['media'] ?? null;

        return [
            'telegram_message_id' => $messageId,
            'text' => (string) ($message['message'] ?? ''),
            'url' => 'https://t.me/'.$username.'/'.$messageId,
            'author_telegram_id' => $authorId,
            'has_media' => is_array($media) && ($media['_'] ?? null) !== 'messageMediaEmpty',
            'posted_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
        ];
    }

    private function authorizedClient(): API
    {
        try {
            $client = $this->mtproto->newClient();
        } catch (Throwable $e) {
            throw new TelegramContentSourceException('Telegram MTProto client is not configured.', previous: $e);
        }

        if (! $this->mtproto->isLoggedIn($client)) {
            throw new TelegramContentSourceException(
                'Telegram session is not authorized. Run php artisan telegram:mtproto:test.'
            );
        }

        return $client;
    }
}
