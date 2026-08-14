<?php

namespace App\Models;

use Database\Factories\ScheduledAutomationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scheduled_automation_id
 * @property int $team_id
 * @property string $status
 * @property array<string, mixed>|null $input_payload
 * @property array<string, mixed>|null $output_payload
 */
#[Fillable([
    'scheduled_automation_id',
    'team_id',
    'started_at',
    'finished_at',
    'status',
    'input_payload',
    'output_payload',
    'error_message',
])]
class ScheduledAutomationRun extends Model
{
    /** @use HasFactory<ScheduledAutomationRunFactory> */
    use HasFactory;

    /**
     * Get the scheduled automation that owns the run.
     *
     * @return BelongsTo<ScheduledAutomation, $this>
     */
    public function scheduledAutomation(): BelongsTo
    {
        return $this->belongsTo(ScheduledAutomation::class);
    }

    /**
     * Get the team that owns the run.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'input_payload' => 'array',
            'output_payload' => 'array',
        ];
    }
}
