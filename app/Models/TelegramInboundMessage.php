<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $direction
 * @property int|null $update_id
 * @property string|null $update_type
 * @property string|null $chat_id
 * @property string|null $from_user_id
 * @property string|null $from_username
 * @property string|null $message_text
 * @property array<string, mixed> $payload
 */
#[Fillable([
    'team_id',
    'direction',
    'update_id',
    'update_type',
    'chat_id',
    'from_user_id',
    'from_username',
    'message_text',
    'payload',
])]
class TelegramInboundMessage extends Model
{
    /**
     * Get the team that owns the inbound message.
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
            'payload' => 'array',
        ];
    }
}
