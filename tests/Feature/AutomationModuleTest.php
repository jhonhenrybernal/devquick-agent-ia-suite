<?php

use App\Enums\TeamRole;
use App\Models\AutomationAgent;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

test('automation overview can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.index', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('automation/index'));
});

test('telegram configuration page can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => [
                'url' => route('automation.telegram.webhook', $team),
                'has_custom_certificate' => false,
                'pending_update_count' => 0,
                'last_error_date' => null,
                'last_error_message' => null,
                'ip_address' => null,
                'max_connections' => 40,
                'allowed_updates' => [],
            ],
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.telegram.edit', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automation/telegram')
            ->where('telegramConfiguration.hasToken', true)
            ->where('telegramConfiguration.botToken', 'telegram-bot-token')
            ->where('telegramConfiguration.hasWebhookSecret', true)
            ->where('telegramConfiguration.webhookSecret', 'telegram-webhook-secret')
            ->where('telegramConfiguration.webhookStatusOk', true)
            ->where('telegramConfiguration.webhookMatchesExpectedUrl', true)
        );
});

test('telegram inbox can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    $message = $team->telegramInboundMessages()->create([
        'update_id' => 1001,
        'update_type' => 'message',
        'chat_id' => '123456789',
        'from_user_id' => '555',
        'from_username' => 'jhonh',
        'message_text' => 'hola inbox',
        'payload' => [
            'update_id' => 1001,
            'message' => [
                'text' => 'hola inbox',
            ],
            'sync' => [
                'status' => 'sent',
                'description' => 'Telegram message processed by the billing agent.',
                'response_text' => 'Hola, ya estoy conectado.',
                'mode' => 'ai',
            ],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.telegram.inbox', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automation/telegram-inbox')
            ->where('messageCount', 1)
            ->where('selectedMessage.id', $message->id)
            ->where('selectedMessage.messageText', 'hola inbox')
            ->where('selectedMessage.syncStatus', 'sent')
            ->where('selectedMessage.syncDescription', 'Telegram message processed by the billing agent.')
        );
});

test('telegram webhook can store inbound messages', function () {
    $team = Team::factory()->create();

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    Log::spy();

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345678,
            'message' => [
                'message_id' => 42,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 555,
                    'username' => 'jhonh',
                ],
                'text' => 'hola equipo',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-webhook-secret',
        ]);

    $response->assertNoContent();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($team): bool {
            return $message === 'Telegram webhook received.'
                && $context['team_id'] === $team->id
                && $context['update_id'] === 912345678
                && $context['chat_id'] === '123456789'
                && $context['from_username'] === 'jhonh'
                && $context['message_text'] === 'hola equipo';
        })
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($team): bool {
            return $message === 'Telegram inbound message stored.'
                && $context['team_id'] === $team->id
                && $context['update_id'] === 912345678
                && $context['chat_id'] === '123456789';
        })
        ->once();

    $this->assertDatabaseHas('telegram_inbound_messages', [
        'team_id' => $team->id,
        'update_id' => 912345678,
        'update_type' => 'message',
        'chat_id' => '123456789',
        'from_user_id' => '555',
        'from_username' => 'jhonh',
        'message_text' => 'hola equipo',
    ]);
});

test('telegram webhook rejects invalid secrets', function () {
    $team = Team::factory()->create();

    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    Log::spy();

    $response = $this
        ->postJson(route('automation.telegram.webhook', $team), [
            'update_id' => 912345679,
            'message' => [
                'message_id' => 43,
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 556,
                    'username' => 'intruder',
                ],
                'text' => 'hack',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

    $response->assertForbidden();

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) use ($team): bool {
            return $message === 'Telegram webhook rejected: secret token mismatch.'
                && $context['team_id'] === $team->id
                && $context['team_slug'] === $team->slug;
        })
        ->once();

    $this->assertDatabaseMissing('telegram_inbound_messages', [
        'update_id' => 912345679,
    ]);
});

test('agents page can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.agents.index', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('automation/agents'));
});

test('automation agent detail page can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
        ]);

    $agent = AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly billing child',
            'target_tool' => 'create_invoice',
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.agents.show', [$team, $agent]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automation/agent')
            ->where('agent.id', $agent->id)
            ->where('mode', 'view')
            ->where('isLocked', false)
        );
});

test('telegram configuration can be saved', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'description' => 'Webhook registered.',
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('automation.telegram.update', $team), [
            'bot_token' => 'telegram-bot-token',
            'bot_username' => 'acme_bot',
            'chat_id' => '123456789',
            'webhook_secret' => 'webhook-secret',
            'is_enabled' => true,
        ]);

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertSessionHasNoErrors();

    $configuration = $team->fresh()->telegramConfiguration;

    expect($configuration?->bot_token)->toBe('telegram-bot-token');
    expect($configuration?->bot_username)->toBe('acme_bot');
    expect($configuration?->chat_id)->toBe('123456789');
    expect($configuration?->webhook_secret)->toBe('webhook-secret');
    expect($configuration?->is_enabled)->toBeTrue();
});

test('telegram webhook can be resynchronized from configuration', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'telegram-webhook-secret',
        'is_enabled' => true,
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'description' => 'Webhook registered.',
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('automation.telegram.sync-webhook', $team));

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertSessionHasNoErrors();

    Http::assertSent(function ($request) use ($team): bool {
        return str_contains($request->url(), '/setWebhook')
            && $request['url'] === route('automation.telegram.webhook', $team)
            && $request['secret_token'] === 'telegram-webhook-secret';
    });
});

test('telegram configuration rejects webhook secrets with unsupported characters', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Http::fake();

    $response = $this
        ->actingAs($user)
        ->patch(route('automation.telegram.update', $team), [
            'bot_token' => 'telegram-bot-token',
            'bot_username' => 'acme_bot',
            'chat_id' => '123456789',
            'webhook_secret' => 'invalid secret with spaces',
            'is_enabled' => true,
        ]);

    $response
        ->assertSessionHasErrors('webhook_secret');

    Http::assertNothingSent();
});

test('telegram configuration generates a safe webhook secret when omitted', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'description' => 'Webhook registered.',
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('automation.telegram.update', $team), [
            'bot_token' => 'telegram-bot-token',
            'bot_username' => 'acme_bot',
            'chat_id' => '123456789',
            'is_enabled' => true,
        ]);

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertSessionHasNoErrors();

    $configuration = $team->fresh()->telegramConfiguration;

    expect($configuration?->webhook_secret)
        ->not->toBeEmpty()
        ->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('telegram configuration regenerates an invalid stored webhook secret on save', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $team->telegramConfiguration()->create([
        'bot_token' => 'telegram-bot-token',
        'chat_id' => '123456789',
        'webhook_secret' => 'invalid secret with spaces',
        'is_enabled' => false,
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'description' => 'Webhook registered.',
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('automation.telegram.update', $team), [
            'bot_username' => 'acme_bot',
            'is_enabled' => true,
        ]);

    $response
        ->assertRedirect(route('automation.telegram.edit', $team))
        ->assertSessionHasNoErrors();

    $configuration = $team->fresh()->telegramConfiguration;

    expect($configuration?->webhook_secret)
        ->not->toBe('invalid secret with spaces')
        ->toMatch('/^[A-Za-z0-9_-]+$/');
});

test('automation agents can be created updated and deleted', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
        ]);

    $createResponse = $this
        ->actingAs($user)
        ->post(route('automation.agents.store', $team), [
            'parent_agent_id' => $parentAgent->id,
            'name' => 'Monthly billing child',
            'description' => 'Create the monthly service invoice',
            'instructions' => 'Create the invoice for the customer every month.',
            'trigger_keyword' => 'monthly invoice',
            'target_tool' => 'create_invoice',
            'is_enabled' => true,
        ]);

    $createResponse
        ->assertRedirect(route('automation.agents.index', $team))
        ->assertSessionHasNoErrors();

    $agent = AutomationAgent::query()
        ->where('team_id', $team->id)
        ->where('name', 'Monthly billing child')
        ->firstOrFail();

    expect($agent->parent_agent_id)->toBe($parentAgent->id);

    $updateResponse = $this
        ->actingAs($user)
        ->patch(route('automation.agents.update', [$team, $agent]), [
            'parent_agent_id' => $parentAgent->id,
            'name' => 'Monthly billing child updated',
            'description' => 'Create the monthly invoice and notify the team',
            'instructions' => 'Create the invoice and send a confirmation to Telegram.',
            'trigger_keyword' => 'invoice updated',
            'target_tool' => 'create_invoice',
            'is_enabled' => false,
        ]);

    $updateResponse
        ->assertRedirect(route('automation.agents.index', $team))
        ->assertSessionHasNoErrors();

    expect($agent->fresh()->name)->toBe('Monthly billing child updated');
    expect($agent->fresh()->parent_agent_id)->toBe($parentAgent->id);
    expect($agent->fresh()->is_enabled)->toBeFalse();

    $deleteResponse = $this
        ->actingAs($user)
        ->delete(route('automation.agents.destroy', [$team, $agent]));

    $deleteResponse
        ->assertRedirect(route('automation.agents.index', $team))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('automation_agents', [
        'id' => $agent->id,
    ]);
});

test('dolibarr mcp web server rejects requests without the shared token', function () {
    config([
        'mcp.shared_token' => 'chatgpt-shared-token',
        'mcp.team_slug' => 'chatgpt-team',
    ]);

    $this
        ->postJson('/mcp/dolibarr', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ])
        ->assertUnauthorized();
});

test('dolibarr mcp web server can call tools with the shared token', function () {
    Http::preventStrayRequests();

    config([
        'mcp.shared_token' => 'chatgpt-shared-token',
        'mcp.team_slug' => 'chatgpt-team',
    ]);

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

    $team = Team::factory()->create([
        'name' => 'ChatGPT Team',
        'slug' => 'chatgpt-team',
    ]);

    $team->dolibarrConfiguration()->create([
        'api_login' => 'dolibarr-user',
        'api_password' => 'dolibarr-password',
        'api_url' => 'https://dolibarr.example.com/api/index.php/explorer/',
    ]);

    $this
        ->postJson('/mcp/dolibarr?token=chatgpt-shared-token', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_invoices',
                'arguments' => [
                    'limit' => 5,
                ],
            ],
        ])
        ->assertOk()
        ->assertSee('FA-2026-0001');
});

test('root automation agents cannot be updated or deleted', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
        ]);

    $updateResponse = $this
        ->actingAs($user)
        ->patch(route('automation.agents.update', [$team, $parentAgent]), [
            'name' => 'Automation orchestrator updated',
            'description' => 'Should not update',
            'instructions' => 'Should not update',
            'trigger_keyword' => 'root',
            'target_tool' => 'route_task',
            'is_enabled' => false,
        ]);

    $updateResponse->assertForbidden();

    $deleteResponse = $this
        ->actingAs($user)
        ->delete(route('automation.agents.destroy', [$team, $parentAgent]));

    $deleteResponse->assertForbidden();
});

test('team members cannot access automation settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($member)
        ->get(route('automation.index', $team));

    $response->assertForbidden();
});

test('admin seeder creates the default automation, billing and DIAN agents', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->firstWhere('email', 'admin@example.com');

    expect($admin)->not->toBeNull();

    $team = $admin?->personalTeam();

    expect($team)->not->toBeNull();

    $parentAgent = AutomationAgent::query()
        ->where('team_id', $team?->id)
        ->whereNull('parent_agent_id')
        ->where('target_tool', 'route_task')
        ->first();

    expect($parentAgent)->not->toBeNull();
    expect($parentAgent?->name)->toBe('Automation orchestrator');

    $billingAgent = AutomationAgent::query()
        ->where('team_id', $team?->id)
        ->where('parent_agent_id', $parentAgent?->id)
        ->where('target_tool', 'create_invoice')
        ->first();

    expect($billingAgent)->not->toBeNull();
    expect($billingAgent?->name)->toBe('Monthly billing agent');

    $dianAgent = AutomationAgent::query()
        ->where('team_id', $team?->id)
        ->where('parent_agent_id', $parentAgent?->id)
        ->where('target_tool', 'dian_tax_review')
        ->first();

    expect($dianAgent)->not->toBeNull();
    expect($dianAgent?->name)->toBe('DIAN compliance agent');

    $trainingAgent = AutomationAgent::query()
        ->where('team_id', $team?->id)
        ->where('parent_agent_id', $parentAgent?->id)
        ->where('target_tool', 'dian_training')
        ->first();

    expect($trainingAgent)->not->toBeNull();
    expect($trainingAgent?->name)->toBe('DIAN training curator');
});

test('dian automation workspace can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $parentAgent = $team->automationAgents()->create([
        'parent_agent_id' => null,
        'name' => 'Automation orchestrator',
        'description' => 'Routes incoming tasks.',
        'instructions' => 'Route the task to the right child.',
        'trigger_keyword' => 'route task',
        'target_tool' => 'route_task',
        'is_enabled' => true,
    ]);

    $team->automationAgents()->create([
        'parent_agent_id' => $parentAgent->id,
        'name' => 'DIAN compliance agent',
        'description' => 'DIAN tax review agent.',
        'instructions' => 'Review tax obligations and deadlines.',
        'trigger_keyword' => 'dian tax',
        'target_tool' => 'dian_tax_review',
        'is_enabled' => true,
    ]);

    $team->automationAgents()->create([
        'parent_agent_id' => $parentAgent->id,
        'name' => 'DIAN training curator',
        'description' => 'Curates approved corrections.',
        'instructions' => 'Capture corrections and normalize them.',
        'trigger_keyword' => 'dian training',
        'target_tool' => 'dian_training',
        'is_enabled' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.dian', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automation/dian')
            ->where('agentCount', 3)
            ->where('readyCount', 3)
            ->where('operationalAgent.name', 'DIAN compliance agent')
            ->where('trainingAgent.name', 'DIAN training curator')
        );
});
