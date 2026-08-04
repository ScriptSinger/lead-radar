<?php

namespace App\Console\Commands;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class TelegramLoginCommand extends Command
{
    protected $signature = 'telegram:login';

    protected $description = 'Авторизация Telegram аккаунта';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash', '');

        if ($apiId <= 0 || $apiHash === '') {
            $this->error('TELEGRAM_API_ID and TELEGRAM_API_HASH must be configured.');

            return self::FAILURE;
        }

        $sessionPath = $this->sessionPath();
        $settings = new Settings;
        $settings->getAppInfo()
            ->setApiId($apiId)
            ->setApiHash($apiHash);

        try {
            $telegram = new API($sessionPath, $settings);

            $telegram->start();
        } catch (Throwable $e) {
            $this->error('Telegram authorization failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Telegram authorized. Session saved to '.$sessionPath);

        return self::SUCCESS;
    }

    private function sessionPath(): string
    {
        $path = (string) config('services.telegram.session_path', 'storage/app/telegram.session');

        return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
