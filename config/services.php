<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        // MTProto user API credentials used for reading Telegram channels.
        // These credentials must never be exposed to the browser.
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
        'phone_number' => env('TELEGRAM_PHONE_NUMBER'),
        'session_path' => env('TELEGRAM_SESSION_PATH', 'storage/app/telegram.session'),
        // Comma-separated chat ids allowed for bot commands (empty = only chat_id)
        'allowed_chat_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TELEGRAM_ALLOWED_CHAT_IDS', env('TELEGRAM_CHAT_ID', '')))
        ))),
        'notify_enabled' => filter_var(env('TELEGRAM_NOTIFY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'ngrok_api_url' => env('NGROK_API_URL', 'http://ngrok:4040'),
    ],

    /*
    |--------------------------------------------------------------------------
    | VK API and scan defaults
    |--------------------------------------------------------------------------
    |
    | VK credentials stay server-side. Runtime scan policy lives in the
    | scan_settings table and is edited in MoonShine.
    |
    */
    'vk' => [
        // Keep the service token on the server only.
        'api_token' => env('VK_API_TOKEN'),
        'api_version' => env('VK_API_VERSION', '5.199'),
        'api_url' => env('VK_API_URL', 'https://api.vk.com/method'),
        'scan_limit' => (int) env('VK_SCAN_LIMIT', 8),
        'scan_with_comments' => filter_var(env('VK_SCAN_WITH_COMMENTS', true), FILTER_VALIDATE_BOOL),
        'scan_group_delay_seconds' => (int) env('VK_SCAN_GROUP_DELAY_SECONDS', 50),
        'scan_schedule' => env('VK_SCAN_SCHEDULE', 'db'), // db = scan_settings.interval_minutes
        'post_window' => env('VK_SCAN_POST_WINDOW', 'since_last_scan'),
    ],

];
