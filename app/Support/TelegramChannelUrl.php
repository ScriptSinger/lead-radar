<?php

namespace App\Support;

use InvalidArgumentException;

class TelegramChannelUrl
{
    public static function isValid(?string $value): bool
    {
        try {
            self::normalize($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @throws InvalidArgumentException */
    public static function normalize(?string $value): string
    {
        $username = self::username($value);

        return 'https://t.me/'.$username;
    }

    /** @throws InvalidArgumentException */
    public static function username(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException(self::validationMessage());
        }

        if (str_starts_with($value, '@')) {
            $username = substr($value, 1);
        } else {
            $parsed = parse_url($value);
            $host = strtolower((string) ($parsed['host'] ?? ''));
            $path = trim((string) ($parsed['path'] ?? ''), '/');

            if (! in_array($host, ['t.me', 'www.t.me', 'telegram.me', 'www.telegram.me'], true)) {
                throw new InvalidArgumentException(self::validationMessage());
            }

            $parts = explode('/', $path);
            if (($parts[0] ?? '') === 's') {
                array_shift($parts);
            }
            if (count($parts) !== 1) {
                throw new InvalidArgumentException(self::validationMessage());
            }
            $username = $parts[0] ?? '';
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $username) !== 1) {
            throw new InvalidArgumentException(self::validationMessage());
        }

        return strtolower($username);
    }

    public static function validationMessage(): string
    {
        return 'Channel must be a public Telegram link (https://t.me/channel_name) or @channel_name.';
    }
}
