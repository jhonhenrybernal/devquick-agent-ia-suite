<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiProviderConfiguration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderConfiguration>
 */
class AiProviderConfigurationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement([
            AiProvider::OpenAi,
            AiProvider::Gemini,
            AiProvider::Ollama,
        ]);

        return [
            'team_id' => Team::factory(),
            'provider' => $provider->value,
            'model' => $provider->defaultModel(),
            'api_key' => $provider->isLocal() ? null : fake()->sha1(),
            'base_url' => $provider->defaultBaseUrl(),
            'is_enabled' => fake()->boolean(),
        ];
    }

    /**
     * Indicate that the configuration uses the given provider.
     */
    public function provider(AiProvider $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider->value,
            'model' => $provider->defaultModel(),
            'base_url' => $provider->defaultBaseUrl(),
        ]);
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
