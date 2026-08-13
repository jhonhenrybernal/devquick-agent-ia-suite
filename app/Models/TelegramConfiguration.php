<?php

namespace App\Models;

use Database\Factories\TelegramConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $bot_token
 * @property string|null $bot_username
 * @property string|null $chat_id
 * @property string|null $webhook_secret
 * @property bool $is_enabled
 */
#[Fillable([
    'team_id',
    'bot_token',
    'bot_username',
    'chat_id',
    'webhook_secret',
    'is_enabled',
])]
class TelegramConfiguration extends Model
{
    /** @use HasFactory<TelegramConfigurationFactory> */
    use HasFactory;

    /**
     * Get the team that owns the Telegram configuration.
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
            'is_enabled' => 'boolean',
            'bot_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }
}
