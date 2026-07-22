<?php

namespace App\Observers;

use App\Jobs\RematchLeadsJob;
use App\Models\Keyword;

/**
 * After a keyword is created/updated, re-run matching on stored content.
 * MoonShine save does not scan VK — only rematches DB posts/comments.
 */
class KeywordObserver
{
    public function saved(Keyword $keyword): void
    {
        // Avoid tight loops if mass-seeding
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            // Seeders / artisan may create many keywords; dispatch once with delay
            RematchLeadsJob::dispatch()->delay(now()->addSeconds(2));

            return;
        }

        RematchLeadsJob::dispatch();
    }

    public function deleted(Keyword $keyword): void
    {
        // Leads cascade via FK; nothing to rematch
    }
}
