<?php

use App\Enums\AiProvider;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Services\AiProvider\AiProviderApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('ai provider configuration can be saved for a team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('automation.ai-provider.update', $team), [
            'provider' => AiProvider::OpenAi->value,
            'model' => 'gpt-4.1-mini',
            'api_key' => 'openai-key',
            'is_enabled' => true,
        ]);

    $response
        ->assertRedirect(route('automation.ai-provider.edit', $team))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'AI provider configuration saved.',
        ]);

    $configuration = $team->fresh()->aiProviderConfiguration;

    expect($configuration)->not->toBeNull();
    expect($configuration?->provider)->toBe(AiProvider::OpenAi->value);
    expect($configuration?->model)->toBe('gpt-4.1-mini');
    expect($configuration?->api_key)->toBe('openai-key');
    expect($configuration?->is_enabled)->toBeTrue();
});

test('ai provider edit page exposes the installation guide url', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::Ollama->value,
        'model' => AiProvider::Ollama->defaultModel(),
        'base_url' => AiProvider::Ollama->defaultBaseUrl(),
        'is_enabled' => true,
    ]);

    $this
        ->actingAs($owner)
        ->get(route('automation.ai-provider.edit', $team))
        ->assertOk()
        ->assertSee('https:\\/\\/www.ollama.com\\/download');
});

test('openai provider connection can be validated', function () {
    Http::fake([
        'https://api.openai.com/v1/models*' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'gpt-4.1-mini'],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::OpenAi->value,
        'model' => 'gpt-4.1-mini',
        'api_key' => 'openai-key',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.ai-provider.test', $team));

    $response
        ->assertRedirect(route('automation.ai-provider.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'OpenAI connection is working with model gpt-4.1-mini.',
        ]);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'https://api.openai.com/v1/models')
            && $request->hasHeader('Authorization', 'Bearer openai-key');
    });
});

test('gemini provider connection can be validated', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
            'models' => [
                ['name' => 'models/gemini-2.5-flash'],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::Gemini->value,
        'model' => 'gemini-2.5-flash',
        'api_key' => 'gemini-key',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.ai-provider.test', $team));

    $response
        ->assertRedirect(route('automation.ai-provider.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Gemini connection is working with model gemini-2.5-flash.',
        ]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models'));
});

test('gemini provider generation exposes the exact failure reason when the response is blocked', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
            'promptFeedback' => [
                'blockReason' => 'SAFETY',
                'blockReasonMessage' => 'Content blocked by safety settings.',
            ],
            'candidates' => [],
        ], 200),
    ]);

    $configuration = Team::factory()->create()->aiProviderConfiguration()->create([
        'provider' => AiProvider::Gemini->value,
        'model' => 'gemini-3.5-flash',
        'api_key' => 'gemini-key',
        'is_enabled' => true,
    ]);

    $result = app(AiProviderApi::class)->generateReply(
        $configuration,
        'Eres un asistente util.',
        'Hola, quiero ver mis facturas.',
    );

    expect($result['valid'])->toBeFalse();
    expect($result['description'])->toBe('Gemini bloqueo la respuesta: SAFETY (Content blocked by safety settings.).');
    expect($result['failure_reason'])->toBe('blocked_safety');
});

test('ollama provider shows a clear message when it is not running', function () {
    Http::fake(function ($request) {
        throw new ConnectionException('Connection refused');
    });

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::Ollama->value,
        'model' => AiProvider::Ollama->defaultModel(),
        'base_url' => AiProvider::Ollama->defaultBaseUrl(),
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.ai-provider.test', $team));

    $response
        ->assertRedirect(route('automation.ai-provider.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Ollama no esta disponible en esta maquina. Instalalo o arracalo antes de volver a probar.',
        ]);
});

test('ollama provider live stream returns chunks and a completion event', function () {
    Http::preventStrayRequests();

    Http::fake([
        'http://localhost:11434/api/chat*' => Http::response(
            "{\"message\":{\"content\":\"Hola\"},\"done\":false}\n{\"message\":{\"content\":\" desde Ollama\"},\"done\":true}\n",
            200,
        ),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::Ollama->value,
        'model' => 'llama3.1',
        'base_url' => 'http://localhost:11434',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->get(route('automation.ai-provider.stream', $team, false).'?prompt=hola');

    $response->assertOk()->assertStreamed();

    expect($response->streamedContent())
        ->toContain('"type":"chunk"')
        ->toContain('Hola')
        ->toContain('desde Ollama')
        ->toContain('"type":"done"');
});
