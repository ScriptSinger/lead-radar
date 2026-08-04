<?php

namespace App\Console\Commands;

use danog\MadelineProto\API;
use App\Services\Telegram\TelegramMtprotoClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telegram:mtproto:test')]
#[Description('Verify Telegram MTProto credentials and the authorized account')]
class TelegramMtprotoTestCommand extends Command
{
    public function handle(TelegramMtprotoClient $mtproto): int
    {
        $this->line('Connecting to Telegram MTProto…');
        $this->line('On first run, enter the code sent to the configured phone number.');

        try {
            $mtproto->assertConfigured();
            $telegram = $mtproto->newClient();
            $this->authorize($telegram, $mtproto->phoneNumber());
            $me = $telegram->getSelf();
        } catch (Throwable $e) {
            $this->error('MTProto connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $name = trim(implode(' ', array_filter([
            (string) ($me['first_name'] ?? ''),
            (string) ($me['last_name'] ?? ''),
        ])));
        $username = (string) ($me['username'] ?? '');
        $account = $name !== '' ? $name : 'Telegram account';
        if ($username !== '') {
            $account .= ' (@'.$username.')';
        }

        $this->info('MTProto is working. Authorized as '.$account.' (ID: '.($me['id'] ?? 'unknown').').');
        $this->line('Session file: '.$mtproto->sessionPath());

        return self::SUCCESS;
    }

    private function authorize(API $telegram, string $phoneNumber): void
    {
        if ($telegram->getAuthorization() === API::NOT_LOGGED_IN) {
            $telegram->phoneLogin($phoneNumber);
        }

        if ($telegram->getAuthorization() === API::WAITING_CODE) {
            $code = (string) $this->secret('Telegram login code');
            $telegram->completePhoneLogin($code);
        }

        if ($telegram->getAuthorization() === API::WAITING_PASSWORD) {
            $password = (string) $this->secret('Telegram 2FA password');
            $telegram->complete2faLogin($password);
        }

        if ($telegram->getAuthorization() !== API::LOGGED_IN) {
            throw new \RuntimeException('Telegram authorization was not completed.');
        }
    }
}
