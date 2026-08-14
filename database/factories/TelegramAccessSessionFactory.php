<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TelegramAccessSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramAccessSession>
 */
class TelegramAccessSessionFactory extends Factory
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
            'telegram_user_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'chat_id' => (string) fake()->numberBetween(100000000, 999999999),
            'telegram_username' => fake()->optional()->userName(),
            'display_name' => fake()->name(),
            'status' => TelegramAccessSession::STATUS_PENDING,
            'requested_at' => now(),
            'approved_at' => null,
            'revoked_at' => null,
            'approved_by_user_id' => null,
            'last_message_at' => now(),
            'notes' => null,
        ];
    }

    /**
     * Indicate that the access session is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TelegramAccessSession::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the access session is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TelegramAccessSession::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
