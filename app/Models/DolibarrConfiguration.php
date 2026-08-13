<?php

namespace App\Models;

use Database\Factories\DolibarrConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property string|null $api_url
 * @property string|null $api_login
 * @property string|null $api_password
 * @property array<int, string>|null $discovered_apis
 * @property \Illuminate\Support\Carbon|null $last_discovered_at
 */
#[Fillable([
    'team_id',
    'api_url',
    'api_login',
    'api_password',
    'discovered_apis',
    'last_discovered_at',
])]
class DolibarrConfiguration extends Model
{
    /** @use HasFactory<DolibarrConfigurationFactory> */
    use HasFactory;

    /**
     * The attributes that should be hidden when serializing the model.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'api_password',
    ];

    /**
     * Get the team that owns the Dolibarr configuration.
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
            'api_url' => 'encrypted',
            'api_password' => 'encrypted',
            'discovered_apis' => 'array',
            'last_discovered_at' => 'datetime',
        ];
    }
}
