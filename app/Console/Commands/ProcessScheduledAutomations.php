<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteScheduledAutomation;
use App\Models\ScheduledAutomation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automation:process-scheduled')]
#[Description('Process due scheduled automations and queue their execution jobs.')]
class ProcessScheduledAutomations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $scheduledAutomations = ScheduledAutomation::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->get();

        foreach ($scheduledAutomations as $scheduledAutomation) {
            ExecuteScheduledAutomation::dispatch($scheduledAutomation->id);
        }

        $this->info(sprintf('Queued %d scheduled automations.', $scheduledAutomations->count()));

        return self::SUCCESS;
    }
}
