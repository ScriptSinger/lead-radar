<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyNewLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $leadId,
    ) {
        $this->onQueue('broadcast.telegram');
    }

    public function handle(TelegramNotifier $notifier): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        // Only notify brand-new pipeline leads
        if ($lead->status !== 'new') {
            return;
        }

        $notifier->notifyNewLead($lead);
    }

    public function failed(?Throwable $e): void
    {
        Log::error('telegram.notify.job_failed', [
            'lead_id' => $this->leadId,
            'error' => $e?->getMessage(),
        ]);
    }
}
