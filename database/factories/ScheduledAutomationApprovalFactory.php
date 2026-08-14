<?php

namespace Database\Factories;

use App\Models\ScheduledAutomation;
use App\Models\ScheduledAutomationApproval;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledAutomationApproval>
 */
class ScheduledAutomationApprovalFactory extends Factory
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
            'source_message_id' => null,
            'approved_by_user_id' => User::factory(),
            'approved_at' => now(),
            'status' => 'approved',
            'notes' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the approval is still pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
    }
}
