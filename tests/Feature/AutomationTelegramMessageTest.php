<?php

use App\Actions\Automation\SendTelegramMessage;
use App\Enums\AiProvider;
use App\Enums\TeamRole;
use App\Models\AutomationAgent;
use App\Models\Team;
use App\Models\TelegramInboundMessage;
use App\Models\User;
use App\Notifications\Automation\TelegramMessageNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramMessage;

test('telegram notifications build the expected payload', function () {
    $notification = new TelegramMessageNotification(
        botToken: 'telegram-bot-token',
        chatId: '123456789',
        content: 'Monthly invoice ready',
    );

    $message = $notification->toTelegram(new AnonymousNotifiable);

    expect($message)->toBeInstanceOf(TelegramMessage::class);
    expect($message->token)->toBe('telegram-bot-token');
    expect($message->toArray())->toMatchArray([
        'chat_id' => '123456789',
        'text' => 'Monthly invoice ready',
    ]);
    expect($message->getPayloadValue('parse_mode'))->toBeNull();
});

test('telegram messages can be dispatched from a team configuration', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $telegramConfiguration = $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.telegram.test', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
        'update_type' => 'assistant_message',
    ]);

    Notification::assertSentOnDemand(TelegramMessageNotification::class, function (
        TelegramMessageNotification $notification,
        array $channels,
        AnonymousNotifiable $notifiable,
    ) use ($telegramConfiguration): bool {
        expect($channels)->toContain('telegram');
        expect($notifiable->routeNotificationFor('telegram'))->toBe($telegramConfiguration->chat_id);

        return $notification->botToken === 'telegram-bot-token'
            && $notification->chatId === $telegramConfiguration->chat_id
            && str_starts_with($notification->content, 'Telegram test from ');
    });
});

test('telegram token can be validated from the interface', function () {
    Http::fake([
        '*' => Http::response([
            'ok' => true,
            'result' => [
                'id' => 12345,
                'is_bot' => true,
                'first_name' => 'Agente Suite',
                'username' => 'Agente_suite_devquick_bot',
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.telegram.validate-token', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Telegram token is valid.',
        ]);

    expect($team->fresh()->telegramConfiguration?->bot_username)->toBe('Agente_suite_devquick_bot');
});

test('telegram chat id can be detected from updates', function () {
    Http::fake([
        '*' => Http::response([
            'ok' => true,
            'result' => [
                [
                    'update_id' => 1,
                    'message' => [
                        'message_id' => 10,
                        'chat' => [
                            'id' => 8589802052,
                            'type' => 'private',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.telegram.detect-chat-id', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Chat ID detected and saved: 8589802052',
        ]);

    expect($team->fresh()->telegramConfiguration?->chat_id)->toBe('8589802052');
});

test('telegram send test shows a clear invalid chat id error', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '8589802052',
        'is_enabled' => true,
    ]);

    $this->mock(SendTelegramMessage::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException("Forbidden: the bot can't send messages to the bot"));
    });

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.telegram.test', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Chat ID invalido: estas intentando enviar el mensaje al bot, no a un usuario, grupo o canal real.',
        ]);
});

test('telegram detect chat id shows a warning when there are no updates yet', function () {
    Http::fake([
        '*' => Http::response([
            'ok' => true,
            'result' => [],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('automation.telegram.detect-chat-id', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'No hay actualizaciones de Telegram todavia. Abre el bot, envia /start y vuelve a intentar.',
        ]);
});

test('telegram webhook can sync an llm reply back to telegram', function () {
    Notification::fake();

    Http::fake([
        'https://api.openai.com/v1/chat/completions*' => Http::response([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Listo, voy a preparar la factura mensual.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::OpenAi->value,
        'model' => 'gpt-4.1-mini',
        'api_key' => 'openai-api-key',
        'is_enabled' => true,
    ]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing agent',
            'target_tool' => 'create_invoice',
            'instructions' => 'Create the monthly invoice and validate the customer.',
            'is_enabled' => true,
        ]);

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345700,
            'message' => [
                'message_id' => 77,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'Hola, quiero conversar con el agente.',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Notification::assertSentOnDemand(TelegramMessageNotification::class, function (
        TelegramMessageNotification $notification,
        array $channels,
        AnonymousNotifiable $notifiable,
    ): bool {
        expect($channels)->toContain('telegram');
        expect($notifiable->routeNotificationFor('telegram'))->toBe('123456789');

        return $notification->botToken === 'telegram-bot-token'
            && $notification->chatId === '123456789'
            && $notification->content === 'Listo, voy a preparar la factura mensual.';
    });

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'inbound',
        'update_id' => 912345700,
        'message_text' => 'Hola, quiero conversar con el agente.',
    ]);

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
        'from_username' => 'Automation orchestrator',
        'message_text' => 'Listo, voy a preparar la factura mensual.',
    ]);
});

test('telegram webhook can stream an ollama reply back to telegram', function () {
    Notification::fake();

    Http::fake([
        'http://localhost:11434/api/chat*' => Http::response(
            "{\"message\":{\"content\":\"Hola\"},\"done\":false}\n{\"message\":{\"content\":\", ya estoy escuchando.\"},\"done\":true}\n",
            200,
        ),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::Ollama->value,
        'model' => 'llama3.1',
        'base_url' => 'http://localhost:11434',
        'is_enabled' => true,
    ]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing agent',
            'target_tool' => 'create_invoice',
            'instructions' => 'Create the monthly invoice and validate the customer.',
            'is_enabled' => true,
        ]);

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345704,
            'message' => [
                'message_id' => 81,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'Hola, probando el agente.',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Notification::assertSentOnDemand(TelegramMessageNotification::class, function (
        TelegramMessageNotification $notification,
        array $channels,
        AnonymousNotifiable $notifiable,
    ): bool {
        expect($channels)->toContain('telegram');
        expect($notifiable->routeNotificationFor('telegram'))->toBe('123456789');

        return $notification->botToken === 'telegram-bot-token'
            && $notification->chatId === '123456789'
            && $notification->content === 'Hola, ya estoy escuchando.';
    });

    $inboundMessage = TelegramInboundMessage::query()
        ->where('team_id', $team->id)
        ->where('direction', 'inbound')
        ->where('update_id', 912345704)
        ->first();

    expect($inboundMessage)->not->toBeNull();
    expect(data_get($inboundMessage?->payload, 'sync.status'))->toBe('sent');
    expect(data_get($inboundMessage?->payload, 'sync.mode'))->toBe('ai');
    expect(data_get($inboundMessage?->payload, 'sync.provider'))->toBe('ollama');
    expect(data_get($inboundMessage?->payload, 'sync.response_text'))->toBe('Hola, ya estoy escuchando.');

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
        'from_username' => 'Automation orchestrator',
        'message_text' => 'Hola, ya estoy escuchando.',
    ]);
});

test('telegram webhook keeps the sync status when telegram delivery fails', function () {
    Notification::fake();

    Http::fake([
        'https://api.openai.com/v1/chat/completions*' => Http::response([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Listo, voy a preparar la factura mensual.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ], 200),
    ]);

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $team->aiProviderConfiguration()->create([
        'provider' => AiProvider::OpenAi->value,
        'model' => 'gpt-4.1-mini',
        'api_key' => 'openai-api-key',
        'is_enabled' => true,
    ]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing agent',
            'target_tool' => 'create_invoice',
            'instructions' => 'Create the monthly invoice and validate the customer.',
            'is_enabled' => true,
        ]);

    $this->mock(SendTelegramMessage::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('Forbidden: the bot cannot send messages to this chat.'));
    });

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345702,
            'message' => [
                'message_id' => 79,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'Hola, quiero conversar con el agente.',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Notification::assertNothingSent();

    $inboundMessage = TelegramInboundMessage::query()
        ->where('team_id', $team->id)
        ->where('direction', 'inbound')
        ->where('update_id', 912345702)
        ->first();

    expect($inboundMessage)->not->toBeNull();
    expect(data_get($inboundMessage?->payload, 'sync.status'))->toBe('telegram_send_failed');
    expect(data_get($inboundMessage?->payload, 'sync.reason'))->toBe('send_failed');
    expect(data_get($inboundMessage?->payload, 'sync.response_text'))->toBe('Listo, voy a preparar la factura mensual.');

    $this->assertDatabaseMissing('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
        'message_text' => 'Listo, voy a preparar la factura mensual.',
    ]);
});

test('telegram webhook sends a fallback reply when the ai provider is not ready', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing agent',
            'target_tool' => 'create_invoice',
            'instructions' => 'Create the monthly invoice and validate the customer.',
            'is_enabled' => true,
        ]);

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345703,
            'message' => [
                'message_id' => 80,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'Hola, no veo el agente listo.',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Notification::assertSentOnDemand(TelegramMessageNotification::class, function (
        TelegramMessageNotification $notification,
        array $channels,
        AnonymousNotifiable $notifiable,
    ): bool {
        expect($channels)->toContain('telegram');
        expect($notifiable->routeNotificationFor('telegram'))->toBe('123456789');

        return $notification->botToken === 'telegram-bot-token'
            && $notification->chatId === '123456789'
            && str_contains($notification->content, 'proveedor IA todavía no está activo');
    });

    $inboundMessage = TelegramInboundMessage::query()
        ->where('team_id', $team->id)
        ->where('direction', 'inbound')
        ->where('update_id', 912345703)
        ->first();

    expect($inboundMessage)->not->toBeNull();
    expect(data_get($inboundMessage?->payload, 'sync.status'))->toBe('sent');
    expect(data_get($inboundMessage?->payload, 'sync.mode'))->toBe('fallback');
    expect(data_get($inboundMessage?->payload, 'sync.reason'))->toBe('ai_provider_not_ready');
    expect(data_get($inboundMessage?->payload, 'sync.response_text'))->toContain('proveedor IA todavía no está activo');

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
    ]);
});

test('telegram webhook can query dolibarr invoices through the billing tool', function () {
    Notification::fake();

    Http::fake([
        'https://dolibarr.example.com/api/index.php/login*' => Http::response([
            'success' => [
                'token' => 'dolibarr-token',
            ],
        ], 200),
        'https://dolibarr.example.com/api/index.php/invoices*' => Http::response([
            [
                'id' => 50,
                'ref' => 'FA-2026-0001',
                'ref_client' => 'CLIENTE-2026-08',
                'socname' => 'Acme Services',
                'date' => '2026-08-11',
                'total_ttc' => 180000,
                'status' => 1,
                'label_status' => 'Open',
            ],
        ], 200),
    ]);

    $team = Team::factory()->create();

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
    ]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing agent',
            'target_tool' => 'create_invoice',
            'instructions' => 'Create the monthly invoice and validate the customer.',
            'is_enabled' => true,
        ]);

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345701,
            'message' => [
                'message_id' => 78,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'Me puede mostrar un resumen de las facturas ya hechas?',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Notification::assertSentOnDemand(TelegramMessageNotification::class, function (
        TelegramMessageNotification $notification,
        array $channels,
        AnonymousNotifiable $notifiable,
    ): bool {
        expect($channels)->toContain('telegram');
        expect($notifiable->routeNotificationFor('telegram'))->toBe('123456789');

        return $notification->botToken === 'telegram-bot-token'
            && $notification->chatId === '123456789'
            && str_contains($notification->content, 'get_invoices')
            && str_contains($notification->content, 'FA-2026-0001');
    });

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'inbound',
        'update_id' => 912345701,
        'message_text' => 'Me puede mostrar un resumen de las facturas ya hechas?',
    ]);

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'direction' => 'outbound',
        'chat_id' => '123456789',
        'from_username' => 'Automation orchestrator',
    ]);
});
