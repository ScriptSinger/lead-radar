<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramNotifier $notifier): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            Log::warning('telegram.webhook.bad_secret');

            return response()->json(['ok' => false], 403);
        }

        $message = $request->input('message') ?? $request->input('edited_message');
        $chatId = data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if ($chatId === null || $text === '') {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) $chatId;

        if (! $this->isAllowedChat($chatId)) {
            Log::info('telegram.webhook.ignored_chat', ['chat_id' => $chatId]);

            return response()->json(['ok' => true]);
        }

        $command = $this->parseCommand($text);

        try {
            match ($command) {
                'start', 'help' => $notifier->sendMessage($this->helpText(), $chatId),
                'ping' => $notifier->sendMessage('pong', $chatId),
                'stats' => $notifier->sendMessage($notifier->formatStats(), $chatId),
                'new' => $this->sendNewLeads($notifier, $chatId),
                'mute' => $this->mute($notifier, $chatId),
                'unmute' => $this->unmute($notifier, $chatId),
                'chatid' => $notifier->sendMessage("chat_id: <code>{$chatId}</code>", $chatId),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('telegram.webhook.handle_failed', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function verifySecret(Request $request): bool
    {
        $secret = (string) config('services.telegram.webhook_secret', '');

        if ($secret === '') {
            return true;
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals($secret, $header);
    }

    private function isAllowedChat(string $chatId): bool
    {
        $allowed = config('services.telegram.allowed_chat_ids', []);

        if ($allowed === [] || $allowed === null) {
            // Fallback: only default chat_id if set
            $default = (string) config('services.telegram.chat_id', '');

            return $default === '' || $default === $chatId;
        }

        return in_array($chatId, array_map('strval', $allowed), true);
    }

    private function parseCommand(string $text): ?string
    {
        $text = trim($text);

        // plain "ping"
        if (mb_strtolower($text) === 'ping') {
            return 'ping';
        }

        if (! str_starts_with($text, '/')) {
            return null;
        }

        // /stats@MyBot -> stats
        $part = explode(' ', $text, 2)[0];
        $part = ltrim($part, '/');
        $part = explode('@', $part)[0];

        return mb_strtolower($part);
    }

    private function helpText(): string
    {
        return implode("\n", [
            '🤖 <b>Lead Radar bot</b>',
            '',
            'Commands:',
            '/stats — counters',
            '/new — last new leads',
            '/mute — stop lead notifications',
            '/unmute — resume notifications',
            '/chatid — show this chat id',
            'ping — pong',
        ]);
    }

    private function sendNewLeads(TelegramNotifier $notifier, string $chatId): void
    {
        $leads = $notifier->latestNewLeads(5);

        if ($leads === []) {
            $notifier->sendMessage('No <b>new</b> leads.', $chatId);

            return;
        }

        $notifier->sendMessage('🆕 Last new leads:', $chatId);

        foreach ($leads as $lead) {
            $notifier->sendMessage($notifier->formatLead($lead), $chatId);
        }
    }

    private function mute(TelegramNotifier $notifier, string $chatId): void
    {
        $notifier->mute();
        $notifier->sendMessage('🔇 Notifications muted.', $chatId);
    }

    private function unmute(TelegramNotifier $notifier, string $chatId): void
    {
        $notifier->unmute();
        $notifier->sendMessage('🔔 Notifications enabled.', $chatId);
    }
}
