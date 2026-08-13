<?php

namespace Database\Factories;

use App\Models\DolibarrConfiguration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DolibarrConfiguration>
 */
class DolibarrConfigurationFactory extends Factory
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
            'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
            'api_login' => fake()->userName(),
            'api_password' => fake()->password(),
            'discovered_apis' => ['login', 'thirdparties', 'products', 'invoices'],
        ];
    }
}
