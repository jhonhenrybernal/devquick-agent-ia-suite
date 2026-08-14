<?php

namespace App\Models;

use Cron\CronExpression;
use Database\Factories\ScheduledAutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $source_message_id
 * @property int|null $parent_agent_id
 * @property int|null $child_agent_id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string $trigger_type
 * @property string|null $cron_expression
 * @property int|null $interval_value
 * @property string|null $interval_unit
 * @property string $timezone
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property string|null $last_result
 * @property array<string, mixed>|null $payload
 */
#[Fillable([
    'team_id',
    'source_message_id',
    'parent_agent_id',
    'child_agent_id',
    'name',
    'description',
    'status',
    'trigger_type',
    'cron_expression',
    'interval_value',
    'interval_unit',
    'timezone',
    'next_run_at',
    'last_run_at',
    'last_result',
    'payload',
])]
class ScheduledAutomation extends Model
{
    /** @use HasFactory<ScheduledAutomationFactory> */
    use HasFactory;

    /**
     * Get the team that owns the schedule.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the source Telegram message for the schedule.
     *
     * @return BelongsTo<TelegramInboundMessage, $this>
     */
    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(TelegramInboundMessage::class, 'source_message_id');
    }

    /**
     * Get the parent agent that orchestrates the schedule.
     *
     * @return BelongsTo<AutomationAgent, $this>
     */
    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(AutomationAgent::class, 'parent_agent_id');
    }

    /**
     * Get the child agent that executes the schedule.
     *
     * @return BelongsTo<AutomationAgent, $this>
     */
    public function childAgent(): BelongsTo
    {
        return $this->belongsTo(AutomationAgent::class, 'child_agent_id');
    }

    /**
     * Get the execution runs for the schedule.
     *
     * @return HasMany<ScheduledAutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledAutomationRun::class);
    }

    /**
     * Get the latest execution run for the schedule.
     *
     * @return HasOne<ScheduledAutomationRun, $this>
     */
    public function latestRun(): HasOne
    {
        return $this->hasOne(ScheduledAutomationRun::class)->latestOfMany();
    }

    /**
     * Get the approvals for the schedule.
     *
     * @return HasMany<ScheduledAutomationApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(ScheduledAutomationApproval::class);
    }

    /**
     * Get the latest approval for the schedule.
     *
     * @return HasOne<ScheduledAutomationApproval, $this>
     */
    public function latestApproval(): HasOne
    {
        return $this->hasOne(ScheduledAutomationApproval::class)->latestOfMany();
    }

    /**
     * Determine the next run date using the configured trigger.
     */
    public function calculateNextRunAt(?Carbon $from = null): ?Carbon
    {
        $from = $from ?? now();

        if ($this->trigger_type === 'cron') {
            if (blank($this->cron_expression) || ! CronExpression::isValidExpression($this->cron_expression)) {
                return null;
            }

            return Carbon::instance(
                CronExpression::factory($this->cron_expression)
                    ->getNextRunDate($from, 0, false, $this->timezone),
            );
        }

        if ($this->trigger_type !== 'interval' || blank($this->interval_value) || blank($this->interval_unit)) {
            return null;
        }

        return match ($this->interval_unit) {
            'minutes' => $from->copy()->addMinutes((int) $this->interval_value),
            'hours' => $from->copy()->addHours((int) $this->interval_value),
            'days' => $from->copy()->addDays((int) $this->interval_value),
            'weeks' => $from->copy()->addWeeks((int) $this->interval_value),
            'months' => $from->copy()->addMonths((int) $this->interval_value),
            default => null,
        };
    }

    /**
     * Recalculate and persist the next run date.
     */
    public function refreshNextRunAt(?Carbon $from = null): void
    {
        $this->forceFill([
            'next_run_at' => $this->calculateNextRunAt($from),
        ])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'payload' => 'array',
            'interval_value' => 'integer',
        ];
    }
}
