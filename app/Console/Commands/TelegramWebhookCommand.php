<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

#[Signature('telegram:setup-webhook {--remove : Remove webhook} {--info : Show webhook info} {--from-ngrok : Resolve public URL via ngrok API}')]
#[Description('Set / remove Telegram webhook (uses TELEGRAM_WEBHOOK_URL or ngrok)')]
class TelegramWebhookCommand extends Command
{
    public function handle(): int
    {
        if (! filled(config('telegram.bots.mybot.token')) && ! filled(config('services.telegram.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is empty');

            return self::FAILURE;
        }

        if ($this->option('info')) {
            return $this->showInfo();
        }

        if ($this->option('remove')) {
            return $this->removeWebhook();
        }

        return $this->setupWebhook();
    }

    private function setupWebhook(): int
    {
        try {
            $url = $this->resolveWebhookUrl();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->error("Webhook URL must be https, got: {$url}");

            return self::FAILURE;
        }

        $params = [
            'url' => $url,
            'drop_pending_updates' => true,
        ];

        $secret = (string) config('services.telegram.webhook_secret', '');
        if ($secret !== '') {
            $params['secret_token'] = $secret;
        }

        try {
            $result = Telegram::setWebhook($params);
        } catch (Throwable $e) {
            $this->error('setWebhook failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Webhook set: {$url}");
        $this->line(is_bool($result) ? ($result ? 'ok' : 'failed') : json_encode($result));

        return self::SUCCESS;
    }

    private function removeWebhook(): int
    {
        try {
            Telegram::removeWebhook();
            $this->info('Webhook removed');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function showInfo(): int
    {
        try {
            $info = Telegram::getWebhookInfo();
            $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveWebhookUrl(): string
    {
        if ($this->option('from-ngrok') || config('services.telegram.webhook_url') === '' || config('services.telegram.webhook_url') === null) {
            $fromNgrok = $this->fetchNgrokPublicUrl();
            if ($fromNgrok) {
                return rtrim($fromNgrok, '/').'/api/telegram/webhook';
            }
        }

        $configured = (string) config('services.telegram.webhook_url', '');
        if ($configured !== '') {
            // Allow base URL or full webhook path
            if (str_contains($configured, '/api/telegram/webhook')) {
                return $configured;
            }

            return rtrim($configured, '/').'/api/telegram/webhook';
        }

        if ($this->option('from-ngrok')) {
            throw new \RuntimeException(
                'Could not resolve ngrok URL. Is ngrok container up? NGROK_API_URL='.
                config('services.telegram.ngrok_api_url', 'http://ngrok:4040')
            );
        }

        throw new \RuntimeException(
            'Set TELEGRAM_WEBHOOK_URL or run with --from-ngrok'
        );
    }

    private function fetchNgrokPublicUrl(): ?string
    {
        $api = rtrim((string) config('services.telegram.ngrok_api_url', 'http://ngrok:4040'), '/');

        try {
            $response = Http::timeout(3)->get("{$api}/api/tunnels");
            if (! $response->successful()) {
                return null;
            }

            $tunnels = $response->json('tunnels') ?? [];
            foreach ($tunnels as $tunnel) {
                $publicUrl = $tunnel['public_url'] ?? null;
                if (is_string($publicUrl) && str_starts_with($publicUrl, 'https://')) {
                    return $publicUrl;
                }
            }

            // fallback any public_url
            foreach ($tunnels as $tunnel) {
                $publicUrl = $tunnel['public_url'] ?? null;
                if (is_string($publicUrl) && str_starts_with($publicUrl, 'http')) {
                    return $publicUrl;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
