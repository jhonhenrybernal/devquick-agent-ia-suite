<?php

namespace App\Models;

use App\Enums\AiProvider;
use Database\Factories\AiProviderConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string $provider
 * @property string|null $model
 * @property string|null $api_key
 * @property string|null $base_url
 * @property bool $is_enabled
 */
#[Fillable([
    'team_id',
    'provider',
    'model',
    'api_key',
    'base_url',
    'is_enabled',
])]
class AiProviderConfiguration extends Model
{
    /** @use HasFactory<AiProviderConfigurationFactory> */
    use HasFactory;

    /**
     * Get the team that owns the AI provider configuration.
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
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Get the provider enum instance.
     */
    public function providerEnum(): AiProvider
    {
        return AiProvider::from($this->provider);
    }
}
