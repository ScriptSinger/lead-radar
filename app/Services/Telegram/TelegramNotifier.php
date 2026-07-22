<?php

namespace App\Services\Telegram;

use App\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Throwable;

class TelegramNotifier
{
    public const MUTE_CACHE_KEY = 'telegram:notify:muted';

    public function __construct(
        private readonly Api $telegram,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('services.telegram.notify_enabled', true)
            && filled(config('services.telegram.bot_token'))
            && filled(config('services.telegram.chat_id'));
    }

    public function isMuted(): bool
    {
        return (bool) Cache::get(self::MUTE_CACHE_KEY, false);
    }

    public function mute(): void
    {
        Cache::forever(self::MUTE_CACHE_KEY, true);
    }

    public function unmute(): void
    {
        Cache::forget(self::MUTE_CACHE_KEY);
    }

    /**
     * @throws TelegramSDKException
     */
    public function sendMessage(string $text, ?string $chatId = null, string $parseMode = 'HTML'): void
    {
        $chatId ??= (string) config('services.telegram.chat_id');

        if ($chatId === '' || ! filled(config('services.telegram.bot_token'))) {
            Log::warning('telegram.send.skipped_no_config');

            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => false,
        ]);
    }

    public function notifyNewLead(Lead $lead): bool
    {
        if (! $this->enabled()) {
            Log::debug('telegram.notify.disabled');

            return false;
        }

        if ($this->isMuted()) {
            Log::debug('telegram.notify.muted', ['lead_id' => $lead->id]);

            return false;
        }

        $lead->loadMissing(['keyword', 'group', 'post', 'comment']);

        try {
            $this->sendMessage($this->formatLead($lead));

            return true;
        } catch (Throwable $e) {
            Log::error('telegram.notify.failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function formatLead(Lead $lead): string
    {
        $keyword = e($lead->keyword?->word ?? '—');
        $group = e($lead->group?->name ?? '—');
        $source = $lead->source_type === 'comment' ? '💬 comment' : '📝 post';
        $score = (int) $lead->score;
        $url = $lead->url;
        $text = $this->snippet((string) $lead->text, 280);

        $lines = [
            '🔔 <b>New lead</b>',
            "🔑 <b>{$keyword}</b> · {$source} · score {$score}",
            "👥 {$group}",
            '',
            $text,
        ];

        if (filled($url)) {
            $lines[] = '';
            $lines[] = "🔗 <a href=\"".e($url)."\">Open in VK</a>";
        }

        $lines[] = '';
        $lines[] = "ID #{$lead->id}";

        return implode("\n", $lines);
    }

    public function formatStats(): string
    {
        $new = \App\Models\Lead::query()->where('status', 'new')->count();
        $processed = \App\Models\Lead::query()->where('status', 'processed')->count();
        $ignored = \App\Models\Lead::query()->where('status', 'ignored')->count();
        $groups = \App\Models\VkGroup::query()->where('active', true)->count();
        $lastScan = \App\Models\VkGroup::query()->whereNotNull('last_scan_at')->max('last_scan_at');
        $muted = $this->isMuted() ? 'yes' : 'no';

        $last = $lastScan
            ? \Carbon\Carbon::parse($lastScan)->format('Y-m-d H:i')
            : 'never';

        return implode("\n", [
            '📊 <b>Lead Radar stats</b>',
            "🆕 new: <b>{$new}</b>",
            "✅ processed: {$processed}",
            "🙈 ignored: {$ignored}",
            "📡 active groups: {$groups}",
            "🕒 last scan: {$last}",
            "🔇 muted: {$muted}",
        ]);
    }

    /**
     * @return list<Lead>
     */
    public function latestNewLeads(int $limit = 5): array
    {
        return \App\Models\Lead::query()
            ->with(['keyword', 'group'])
            ->where('status', 'new')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function snippet(string $text, int $max): string
    {
        $text = trim(preg_replace("/\s+/u", ' ', $text) ?? '');
        $text = e($text);

        if (mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max).'…';
        }

        return $text;
    }
}
