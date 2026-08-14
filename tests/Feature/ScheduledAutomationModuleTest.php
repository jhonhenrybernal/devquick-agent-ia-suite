<?php

use App\Enums\TeamRole;
use App\Jobs\ExecuteScheduledAutomation;
use App\Models\AutomationAgent;
use App\Models\ScheduledAutomation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

test('scheduled automations page can be rendered by team owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $parentAgent = AutomationAgent::factory()
        ->for($team)
        ->create([
            'name' => 'Automation orchestrator',
            'target_tool' => 'route_task',
            'is_enabled' => true,
        ]);

    $childAgent = AutomationAgent::factory()
        ->for($team)
        ->childOf($parentAgent)
        ->create([
            'name' => 'Monthly scheduler',
            'target_tool' => 'create_invoice',
            'is_enabled' => true,
        ]);

    $scheduledAutomation = ScheduledAutomation::factory()
        ->for($team)
        ->create([
            'parent_agent_id' => $parentAgent->id,
            'child_agent_id' => $childAgent->id,
            'status' => 'active',
            'trigger_type' => 'interval',
            'next_run_at' => now()->addHour(),
        ]);

    $response = $this
        ->actingAs($user)
        ->get(route('automation.scheduled-automations.index', $team));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('automation/scheduled-automations')
            ->where('scheduledAutomations.0.id', $scheduledAutomation->id)
            ->where('scheduledAutomations.0.name', $scheduledAutomation->name)
        );
});

test('scheduled automation command queues due automations', function () {
    Bus::fake();

    $team = Team::factory()->create();
    $parentAgent = AutomationAgent::factory()->for($team)->create([
        'name' => 'Automation orchestrator',
        'target_tool' => 'route_task',
        'is_enabled' => true,
    ]);

    $childAgent = AutomationAgent::factory()->for($team)->childOf($parentAgent)->create([
        'name' => 'Monthly scheduler',
        'target_tool' => 'create_invoice',
        'is_enabled' => true,
    ]);

    ScheduledAutomation::factory()
        ->for($team)
        ->create([
            'parent_agent_id' => $parentAgent->id,
            'child_agent_id' => $childAgent->id,
            'status' => 'active',
            'trigger_type' => 'interval',
            'next_run_at' => now()->subMinute(),
        ]);

    $this->artisan('automation:process-scheduled')
        ->assertExitCode(0);

    Bus::assertDispatched(ExecuteScheduledAutomation::class, 1);
});
