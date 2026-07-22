<?php

namespace App\Observers;

use App\Jobs\NotifyNewLeadJob;
use App\Models\Lead;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        if ($lead->status !== 'new') {
            return;
        }

        if (! config('services.telegram.notify_enabled', true)) {
            return;
        }

        NotifyNewLeadJob::dispatch($lead->id);
    }
}
