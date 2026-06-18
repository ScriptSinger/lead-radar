<?php

namespace App\Console\Commands;

use danog\MadelineProto\API;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;


class TelegramLoginCommand extends Command
{

    protected $signature = 'telegram:login';

    protected $description = 'Авторизация Telegram аккаунта';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessionPath = storage_path('app/telegram.session');

        $telegram = new API($sessionPath);

        $telegram->start();

        $this->info('Telegram авторизован');
    }
}
