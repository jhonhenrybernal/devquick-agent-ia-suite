<?php

use App\Enums\TeamRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;

test('admin user seeder creates the default administrator account', function () {
    $this->seed(AdminUserSeeder::class);

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->name)->toBe('Administrator');
    expect($user?->currentTeam?->is_personal)->toBeTrue();
    expect($user?->teamRole($user->currentTeam))->toBe(TeamRole::Owner);
});
