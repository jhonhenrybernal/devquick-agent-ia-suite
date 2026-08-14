<?php

namespace App\Models;

use Database\Factories\ScheduledAutomationApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scheduled_automation_id
 * @property int $team_id
 * @property int|null $source_message_id
 * @property int|null $approved_by_user_id
 * @property string $status
 * @property string|null $notes
 */
#[Fillable([
    'scheduled_automation_id',
    'team_id',
    'source_message_id',
    'approved_by_user_id',
    'approved_at',
    'status',
    'notes',
])]
class ScheduledAutomationApproval extends Model
{
    /** @use HasFactory<ScheduledAutomationApprovalFactory> */
    use HasFactory;

    /**
     * Get the scheduled automation being approved.
     *
     * @return BelongsTo<ScheduledAutomation, $this>
     */
    public function scheduledAutomation(): BelongsTo
    {
        return $this->belongsTo(ScheduledAutomation::class);
    }

    /**
     * Get the owning team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the approving user.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Get the source Telegram message.
     *
     * @return BelongsTo<TelegramInboundMessage, $this>
     */
    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(TelegramInboundMessage::class, 'source_message_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
