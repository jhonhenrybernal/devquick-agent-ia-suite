<?php

namespace Database\Factories;

use App\Models\ScheduledAutomation;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledAutomation>
 */
class ScheduledAutomationFactory extends Factory
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
            'source_message_id' => null,
            'parent_agent_id' => null,
            'child_agent_id' => null,
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => 'draft',
            'trigger_type' => 'interval',
            'cron_expression' => null,
            'interval_value' => 1,
            'interval_unit' => 'months',
            'timezone' => config('app.timezone'),
            'next_run_at' => now()->addMonth(),
            'last_run_at' => null,
            'last_result' => null,
            'payload' => [
                'context' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Indicate that the automation is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the automation is due to run immediately.
     */
    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'next_run_at' => now()->subMinute(),
        ]);
    }
}
