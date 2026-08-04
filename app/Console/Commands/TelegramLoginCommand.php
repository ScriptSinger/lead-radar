<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramMtprotoClient;
use Illuminate\Console\Command;
use Throwable;

class TelegramLoginCommand extends Command
{
    protected $signature = 'telegram:login';

    protected $description = 'Авторизация Telegram аккаунта';

    /**
     * Execute the console command.
     */
    public function handle(TelegramMtprotoClient $mtproto): int
    {
        try {
            $mtproto->assertConfigured(requirePhoneNumber: false);
            $telegram = $mtproto->newClient();
            $telegram->start();
        } catch (Throwable $e) {
            $this->error('Telegram authorization failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Telegram authorized. Session saved to '.$mtproto->sessionPath());

        return self::SUCCESS;
    }
}
