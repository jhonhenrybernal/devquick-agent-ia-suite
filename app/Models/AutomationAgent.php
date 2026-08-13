<?php

namespace App\Models;

use Database\Factories\AutomationAgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $parent_agent_id
 * @property string $name
 * @property string|null $description
 * @property string $instructions
 * @property string|null $trigger_keyword
 * @property string $target_tool
 * @property bool $is_enabled
 * @property-read AutomationAgent|null $parentAgent
*/
#[Fillable([
    'team_id',
    'parent_agent_id',
    'name',
    'description',
    'instructions',
    'trigger_keyword',
    'target_tool',
    'is_enabled',
])]
class AutomationAgent extends Model
{
    /** @use HasFactory<AutomationAgentFactory> */
    use HasFactory;

    /**
     * Get the team that owns the automation agent.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the parent automation agent.
     *
     * @return BelongsTo<AutomationAgent, $this>
     */
    public function parentAgent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agent_id');
    }

    /**
     * Get the child automation agents.
     *
     * @return HasMany<AutomationAgent, $this>
     */
    public function childAgents(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agent_id');
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
        ];
    }
}
