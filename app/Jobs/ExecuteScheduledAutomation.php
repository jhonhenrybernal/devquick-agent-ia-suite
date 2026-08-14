<?php

namespace App\Jobs;

use App\Actions\Automation\SendTelegramMessage;
use App\Models\ScheduledAutomation;
use App\Models\ScheduledAutomationApproval;
use App\Models\ScheduledAutomationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteScheduledAutomation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $scheduledAutomationId)
    {
        //
    }

    public function handle(SendTelegramMessage $sendTelegramMessage): void
    {
        $scheduledAutomation = ScheduledAutomation::query()
            ->with(['team.telegramConfiguration', 'parentAgent', 'childAgent'])
            ->find($this->scheduledAutomationId);

        if (! $scheduledAutomation instanceof ScheduledAutomation) {
            Log::warning('Scheduled automation skipped: record not found.', [
                'scheduled_automation_id' => $this->scheduledAutomationId,
            ]);

            return;
        }

        if ($scheduledAutomation->status !== 'active' || $scheduledAutomation->next_run_at === null || $scheduledAutomation->next_run_at->isFuture()) {
            return;
        }

        $run = ScheduledAutomationRun::query()->create([
            'scheduled_automation_id' => $scheduledAutomation->id,
            'team_id' => $scheduledAutomation->team_id,
            'started_at' => now(),
            'status' => 'running',
            'input_payload' => [
                'scheduled_automation_id' => $scheduledAutomation->id,
                'source_message_id' => $scheduledAutomation->source_message_id,
                'parent_agent_id' => $scheduledAutomation->parent_agent_id,
                'child_agent_id' => $scheduledAutomation->child_agent_id,
            ],
        ]);

        $approval = ScheduledAutomationApproval::query()
            ->where('scheduled_automation_id', $scheduledAutomation->id)
            ->latest()
            ->first();

        $resultMessage = $approval && $approval->status === 'rejected'
            ? 'La tarea programada fue omitida porque todavía no está aprobada.'
            : sprintf(
                'Tarea programada "%s" ejecutada correctamente.',
                $scheduledAutomation->name,
            );

        try {
            $scheduledAutomation->forceFill([
                'last_run_at' => now(),
                'last_result' => $resultMessage,
            ])->save();

            $scheduledAutomation->refreshNextRunAt(now());

            $run->forceFill([
                'finished_at' => now(),
                'status' => 'success',
                'output_payload' => [
                    'message' => $resultMessage,
                    'next_run_at' => $scheduledAutomation->next_run_at?->toISOString(),
                ],
            ])->save();

            $telegramConfiguration = $scheduledAutomation->team->telegramConfiguration;

            if ($telegramConfiguration && $approval?->status !== 'rejected') {
                $sendTelegramMessage->handle(
                    $telegramConfiguration,
                    $resultMessage,
                    $telegramConfiguration->chat_id,
                    [
                        'update_type' => 'assistant_message',
                        'from_username' => 'Scheduled automation',
                        'generated_by' => [
                            'mode' => 'scheduled',
                            'scheduled_automation_id' => $scheduledAutomation->id,
                            'scheduled_automation_run_id' => $run->id,
                        ],
                    ],
                );
            }
        } catch (Throwable $exception) {
            $run->forceFill([
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();

            $scheduledAutomation->forceFill([
                'last_result' => $exception->getMessage(),
            ])->save();

            Log::warning('Scheduled automation execution failed.', [
                'scheduled_automation_id' => $scheduledAutomation->id,
                'scheduled_automation_run_id' => $run->id,
                'description' => $exception->getMessage(),
            ]);
        }
    }
}
