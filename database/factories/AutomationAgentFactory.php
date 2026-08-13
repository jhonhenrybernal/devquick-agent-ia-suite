<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\AutomationAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationAgent>
 */
class AutomationAgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'parent_agent_id' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraphs(2, true),
            'trigger_keyword' => fake()->optional()->word(),
            'target_tool' => 'create_invoice',
            'is_enabled' => true,
        ];
    }

    /**
     * Indicate that the agent is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Indicate that the agent belongs to a parent agent.
     */
    public function childOf(AutomationAgent $parentAgent): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $parentAgent->team_id,
            'parent_agent_id' => $parentAgent->id,
        ]);
    }
}
