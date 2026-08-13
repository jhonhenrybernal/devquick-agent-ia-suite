<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TelegramConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramConfiguration>
 */
class TelegramConfigurationFactory extends Factory
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
            'bot_token' => fake()->sha1(),
            'bot_username' => fake()->userName(),
            'chat_id' => (string) fake()->numerify('########'),
            'webhook_secret' => fake()->sha1(),
            'is_enabled' => fake()->boolean(),
        ];
    }

    /**
     * Indicate that the configuration is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => true,
        ]);
    }
}
