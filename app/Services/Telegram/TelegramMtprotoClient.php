<?php

namespace App\Services\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Support\Str;

/**
 * Creates authenticated MTProto clients for reading Telegram channel data.
 *
 * The session stays in local server storage. Never expose its path or contents
 * in web responses, logs, or queue payloads.
 */
class TelegramMtprotoClient
{
    public function assertConfigured(bool $requirePhoneNumber = true): void
    {
        $missing = [];

        if ($this->apiId() <= 0) {
            $missing[] = 'TELEGRAM_API_ID';
        }
        if ($this->apiHash() === '') {
            $missing[] = 'TELEGRAM_API_HASH';
        }
        if ($requirePhoneNumber && $this->phoneNumber() === '') {
            $missing[] = 'TELEGRAM_PHONE_NUMBER';
        }

        if ($missing !== []) {
            throw new TelegramMtprotoConfigurationException(
                'Missing MTProto configuration: '.implode(', ', $missing).'.'
            );
        }
    }

    /** Create an API client using the configured account and local session. */
    public function newClient(): API
    {
        $this->assertConfigured(requirePhoneNumber: false);
        $this->ensureSessionDirectory();

        $settings = new Settings;
        $settings->getAppInfo()
            ->setApiId($this->apiId())
            ->setApiHash($this->apiHash());

        return new API($this->sessionPath(), $settings);
    }

    public function isLoggedIn(API $client): bool
    {
        return $client->getAuthorization() === API::LOGGED_IN;
    }

    /** A session file existing alone is not proof of a valid Telegram login. */
    public function sessionExists(): bool
    {
        return is_file($this->sessionPath()) && filesize($this->sessionPath()) > 0;
    }

    public function sessionPath(): string
    {
        $path = trim((string) config('services.telegram.session_path', 'storage/app/telegram.session'));
        if ($path === '') {
            $path = 'storage/app/telegram.session';
        }

        return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    public function phoneNumber(): string
    {
        return trim((string) config('services.telegram.phone_number', ''));
    }

    private function apiId(): int
    {
        return (int) config('services.telegram.api_id');
    }

    private function apiHash(): string
    {
        return trim((string) config('services.telegram.api_hash', ''));
    }

    private function ensureSessionDirectory(): void
    {
        $directory = dirname($this->sessionPath());

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new TelegramMtprotoConfigurationException(
                "Cannot create Telegram session directory: {$directory}"
            );
        }

        if (! is_writable($directory)) {
            throw new TelegramMtprotoConfigurationException(
                "Telegram session directory is not writable: {$directory}"
            );
        }
    }
}
