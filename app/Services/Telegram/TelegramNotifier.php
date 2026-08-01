<?php

namespace App\Services\Telegram;

use App\Models\Lead;
use App\Models\ScanSetting;
use App\Models\VkGroup;
use Carbon\Carbon;
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
    public function sendMessage(string $text, ?string $chatId = null, string $parseMode = 'HTML', $replyMarkup = null): void
    {
        $chatId ??= (string) config('services.telegram.chat_id');

        if ($chatId === '' || ! filled(config('services.telegram.bot_token'))) {
            Log::warning('telegram.send.skipped_no_config');

            return;
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => false,
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        $this->telegram->sendMessage($params);
    }

    /**
     * @throws TelegramSDKException
     */
    public function editMessage(string $text, string $chatId, string $messageId, string $parseMode = 'HTML', $replyMarkup = null): void
    {
        if (! filled(config('services.telegram.bot_token'))) {
            return;
        }

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = is_array($replyMarkup) ? json_encode($replyMarkup) : $replyMarkup;
        }

        $this->telegram->editMessageText($params);
    }

    /**
     * @throws TelegramSDKException
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): void
    {
        if (! filled(config('services.telegram.bot_token'))) {
            return;
        }

        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    public function formatScanStatus(): array
    {
        $settings = ScanSetting::current();
        $enabled = $settings->schedule_enabled;
        $status = $enabled ? '🟢 <b>Running</b>' : '🔴 <b>Stopped</b>';

        $tz = config('app.timezone', 'UTC');

        $lastScanStr = 'never';
        if ($settings->last_dispatched_at) {
            $lastTime = $settings->last_dispatched_at->timezone($tz);
            $diff = $lastTime->locale('ru')->diffForHumans();
            $lastScanStr = $lastTime->format('H:i:s').' ('.$diff.')';
        }

        $intervalStr = $settings->normalizedIntervalMinutes().' min';

        $text = implode("\n", [
            '🤖 <b>Scan Control</b>',
            "Status: {$status}",
            '',
            "Interval: {$intervalStr}",
            "Last scan: <code>{$lastScanStr}</code>",
        ]);

        $buttons = [];
        if ($enabled) {
            $buttons[] = [['text' => '⛔ Stop scans', 'callback_data' => 'scan_stop']];
        } else {
            $buttons[] = [['text' => '🚀 Start scans', 'callback_data' => 'scan_start']];
        }

        $buttons[] = [['text' => '🔄 Refresh', 'callback_data' => 'scan_refresh']];

        return [
            'text' => $text,
            'markup' => ['inline_keyboard' => $buttons],
        ];
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
            $lines[] = '🔗 <a href="'.e($url).'">Open in VK</a>';
        }

        $lines[] = '';
        $lines[] = "ID #{$lead->id}";

        return implode("\n", $lines);
    }

    public function formatStats(): string
    {
        $new = Lead::query()->where('status', 'new')->count();
        $processed = Lead::query()->where('status', 'processed')->count();
        $ignored = Lead::query()->where('status', 'ignored')->count();
        $groups = VkGroup::query()->where('active', true)->count();
        $lastScan = VkGroup::query()->whereNotNull('last_scan_at')->max('last_scan_at');
        $muted = $this->isMuted() ? 'yes' : 'no';

        $last = $lastScan
            ? Carbon::parse($lastScan)->format('Y-m-d H:i')
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
        return Lead::query()
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
