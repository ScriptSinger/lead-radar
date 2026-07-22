<?php

namespace App\Support;

class VkUrl
{
    /**
     * Accept public VK group/community URLs (vk.com / vk.ru / m.vk.com).
     */
    public static function isValid(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        try {
            $parsed = parse_url(trim($url));
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if (! preg_match('/(^|\.)vk\.(com|ru)$/', $host)) {
            return false;
        }

        $path = (string) ($parsed['path'] ?? '');

        // Require some path beyond "/" (group slug or id)
        return $path !== '' && $path !== '/';
    }

    public static function validationMessage(): string
    {
        return 'URL must be a valid VK link (https://vk.com/... or https://vk.ru/...).';
    }
}
