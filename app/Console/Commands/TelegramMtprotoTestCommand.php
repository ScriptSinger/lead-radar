<?php

namespace App\Console\Commands;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('telegram:mtproto:test')]
#[Description('Verify Telegram MTProto credentials and the authorized account')]
class TelegramMtprotoTestCommand extends Command
{
    public function handle(): int
    {
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash', '');
        $phoneNumber = (string) config('services.telegram.phone_number', '');

        if ($apiId <= 0 || $apiHash === '' || $phoneNumber === '') {
            $this->error('Set TELEGRAM_API_ID, TELEGRAM_API_HASH and TELEGRAM_PHONE_NUMBER in .env.');

            return self::FAILURE;
        }

        $sessionPath = $this->sessionPath();
        $this->line('Connecting to Telegram MTProto…');
        $this->line('On first run, enter the code sent to the configured phone number.');

        $settings = new Settings;
        $settings->getAppInfo()
            ->setApiId($apiId)
            ->setApiHash($apiHash);

        try {
            $telegram = new API($sessionPath, $settings);
            $this->authorize($telegram, $phoneNumber);
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
        $this->line('Session file: '.$sessionPath);

        return self::SUCCESS;
    }

    private function sessionPath(): string
    {
        $path = (string) config('services.telegram.session_path', 'storage/app/telegram.session');

        return Str::startsWith($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
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
