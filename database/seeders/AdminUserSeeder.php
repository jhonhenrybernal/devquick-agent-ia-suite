<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = (string) env('ADMIN_USER_NAME', 'Administrator');
        $email = (string) env('ADMIN_USER_EMAIL', 'admin@example.com');
        $password = (string) env('ADMIN_USER_PASSWORD', 'password');

        $user = User::query()->firstWhere('email', $email);

        if ($user === null) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        } else {
            $user->update([
                'name' => $name,
                'password' => $password,
            ]);
        }

        if ($user->personalTeam() === null) {
            $team = Team::query()->create([
                'name' => $name."'s Team",
                'slug' => Str::slug($name."'s Team"),
                'is_personal' => true,
            ]);

            $team->members()->attach($user, [
                'role' => TeamRole::Owner->value,
            ]);

            $user->switchTeam($team);
        }

        $team = $user->personalTeam();

        if ($team instanceof Team) {
            $this->callWith(AutomationAgentSeeder::class, [
                'team' => $team,
            ]);

            $this->callWith(DianAgentSeeder::class, [
                'team' => $team,
            ]);
        }
    }
}
