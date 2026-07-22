<?php

namespace App\Providers;

use App\Models\Keyword;
use App\Models\Lead;
use App\Observers\KeywordObserver;
use App\Observers\LeadObserver;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Api;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramNotifier::class, function ($app) {
            return new TelegramNotifier($app->make(Api::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Keyword::observe(KeywordObserver::class);
        Lead::observe(LeadObserver::class);

        // Dead-letter style alert for any permanently failed queue job
        Queue::failing(function (JobFailed $event): void {
            Log::error('queue.job_failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'name' => $event->job->resolveName(),
                'error' => $event->exception->getMessage(),
            ]);

            // ScanVkGroupJob already alerts in failed(); avoid double spam for it
            if (str_contains($event->job->resolveName(), 'ScanVkGroupJob')) {
                return;
            }

            try {
                if (! config('services.telegram.notify_enabled', true)) {
                    return;
                }

                /** @var TelegramNotifier $notifier */
                $notifier = app(TelegramNotifier::class);
                if (! $notifier->enabled() || $notifier->isMuted()) {
                    return;
                }

                $notifier->sendMessage(implode("\n", [
                    '🚨 <b>Queue job failed</b>',
                    '📦 '.e($event->job->resolveName()),
                    '🧵 '.e((string) $event->job->getQueue()),
                    '❌ '.e(mb_substr($event->exception->getMessage(), 0, 400)),
                ]));
            } catch (Throwable $e) {
                Log::warning('queue.failing_alert_failed', ['error' => $e->getMessage()]);
            }
        });
    }
}
