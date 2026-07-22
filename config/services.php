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

    'parser' => [
        'url' => env('PARSER_URL', 'http://parser:3000'),
        // Comments with offset pagination can take >60s
        'timeout' => (int) env('PARSER_TIMEOUT', 180),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
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
    | VK scan automation (Phase 5)
    */
    'vk' => [
        'scan_limit' => (int) env('VK_SCAN_LIMIT', 6),
        'scan_with_comments' => filter_var(env('VK_SCAN_WITH_COMMENTS', true), FILTER_VALIDATE_BOOL),
        // Stagger between group jobs to reduce VK/parser pressure
        'scan_group_delay_seconds' => (int) env('VK_SCAN_GROUP_DELAY_SECONDS', 45),
        // Schedule interval for schedule:work / cron (used in routes/console.php docs only if needed)
        'scan_schedule' => env('VK_SCAN_SCHEDULE', 'hourly'),
    ],

];
