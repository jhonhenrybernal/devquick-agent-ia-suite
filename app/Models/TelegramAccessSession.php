<?php

namespace App\Models;

use Database\Factories\TelegramAccessSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $telegram_user_id
 * @property string|null $chat_id
 * @property string|null $telegram_username
 * @property string|null $display_name
 * @property string $status
 * @property Carbon|null $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $revoked_at
 * @property int|null $approved_by_user_id
 * @property Carbon|null $last_message_at
 * @property string|null $notes
 */
#[Fillable([
    'team_id',
    'telegram_user_id',
    'chat_id',
    'telegram_username',
    'display_name',
    'status',
    'requested_at',
    'approved_at',
    'revoked_at',
    'approved_by_user_id',
    'last_message_at',
    'notes',
])]
class TelegramAccessSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVOKED = 'revoked';

    /** @use HasFactory<TelegramAccessSessionFactory> */
    use HasFactory;

    /**
     * Get the team that owns the Telegram access session.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that approved the Telegram access session.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
