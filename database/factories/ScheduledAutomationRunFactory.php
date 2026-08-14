<?php

namespace Database\Factories;

use App\Models\ScheduledAutomation;
use App\Models\ScheduledAutomationRun;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledAutomationRun>
 */
class ScheduledAutomationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_automation_id' => ScheduledAutomation::factory(),
            'team_id' => Team::factory(),
            'started_at' => now(),
            'finished_at' => now()->addMinute(),
            'status' => 'success',
            'input_payload' => [
                'context' => fake()->sentence(),
            ],
            'output_payload' => [
                'summary' => fake()->sentence(),
            ],
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the run failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }
}
