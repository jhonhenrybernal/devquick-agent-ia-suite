<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('dolibarr configuration can be saved for a team', function () {
    Http::preventStrayRequests();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-access-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/explorer*' => Http::response(
            '<html><body><h1>login</h1><h2>thirdparties</h2><h2>products</h2><h2>invoices</h2></body></html>',
            200,
        ),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('automation.dolibarr.update', $team), [
            'api_login' => 'dolibarr-user',
            'api_password' => 'dolibarr-password',
            'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
        ]);

    $response
        ->assertRedirect(route('automation.dolibarr.edit', $team))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Dolibarr respondio correctamente y se detectaron 4 operaciones.',
        ]);

    expect($team->fresh()->dolibarrConfiguration?->api_login)->toBe('dolibarr-user');
    expect($team->fresh()->dolibarrConfiguration?->api_password)->toBe('dolibarr-password');
    expect($team->fresh()->dolibarrConfiguration?->api_url)->toBe('https://dolibarr.example.com/api/index.php/explorer/');
    expect($team->fresh()->dolibarrConfiguration?->discovered_apis)->toBe([
        'login',
        'thirdparties',
        'products',
        'invoices',
    ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://dolibarr.example.com/api/index.php/login')
            && str_contains($request->url(), 'login=dolibarr-user')
            && str_contains($request->url(), 'password=dolibarr-password');
    });

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://dolibarr.example.com/api/index.php/explorer')
            && $request->hasHeader('DOLAPIKEY', 'dolibarr-access-token');
    });
});

test('dolibarr edit page exposes the official setup guide', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
    ]);

    $this
        ->actingAs($owner)
        ->get(route('automation.dolibarr.edit', $team))
        ->assertOk()
        ->assertSee('Module_Web_Services_API_REST')
        ->assertSee('apiLogin');
});

test('dolibarr connection can be validated', function () {
    Http::preventStrayRequests();

    config(['services.dolibarr.base_url' => 'https://dolibarr-default.example.com']);

    Http::fake([
        'https://dolibarr-default.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-default-token',
            ],
        ], 200),
        'https://dolibarr-default.example.com/api/index.php/explorer*' => Http::response('<html>ok</html>', 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr-default.example.com/api/index.php/explorer/',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.dolibarr.test', $team));

    $response
        ->assertRedirect(route('automation.dolibarr.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Dolibarr connection is working.',
        ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://dolibarr-default.example.com/api/index.php/login')
            && str_contains($request->url(), 'login=dolibarr-user')
            && str_contains($request->url(), 'password=dolibarr-password');
    });
});

test('dolibarr connection uses the configured api url when present', function () {
    Http::preventStrayRequests();

    config(['services.dolibarr.base_url' => 'https://dolibarr-default.example.com']);

    Http::fake([
        'https://dolibarr-custom.example.com/api/index.php/login*' => Http::response([
            'token' => 'dolibarr-custom-token',
        ], 200),
        'https://dolibarr-custom.example.com/api/index.php/explorer*' => Http::response('<html>ok</html>', 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr-custom.example.com/api/index.php/explorer/',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.dolibarr.test', $team));

    $response
        ->assertRedirect(route('automation.dolibarr.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Dolibarr connection is working.',
        ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://dolibarr-custom.example.com/api/index.php/login')
            && str_contains($request->url(), 'login=dolibarr-user')
            && str_contains($request->url(), 'password=dolibarr-password');
    });

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://dolibarr-custom.example.com/api/index.php/explorer')
            && $request->hasHeader('DOLAPIKEY', 'dolibarr-custom-token');
    });
});

test('dolibarr edit page exposes the configured api url status', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
        'discovered_apis' => ['login', 'thirdparties', 'products', 'invoices'],
    ]);

    $this
        ->actingAs($owner)
        ->get(route('automation.dolibarr.edit', $team))
        ->assertOk()
        ->assertSee('importantApis')
        ->assertSee('thirdparties');
});

test('dolibarr connection shows a clear error when the server is unavailable', function () {
    Http::preventStrayRequests();

    config(['services.dolibarr.base_url' => 'https://dolibarr.example.com']);

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.dolibarr.test', $team));

    $response
        ->assertRedirect(route('automation.dolibarr.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'No se pudo conectar con Dolibarr. Revisa la URL base y que la API REST este activa.',
        ]);
});

test('dolibarr configuration is not saved when the automatic connection test fails', function () {
    Http::preventStrayRequests();

    Http::fake(function () {
        throw new ConnectionException('Connection refused');
    });

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('automation.dolibarr.update', $team), [
            'api_login' => 'dolibarr-user',
            'api_password' => 'dolibarr-password',
            'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
        ]);

    $response
        ->assertRedirect(route('automation.dolibarr.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'No se pudo conectar con Dolibarr. Revisa la URL base y que la API REST este activa.',
        ]);

    expect($team->fresh()->dolibarrConfiguration)->toBeNull();
});
