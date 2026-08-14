<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\ScheduledAutomationRequest;
use App\Models\AutomationAgent;
use App\Models\ScheduledAutomation;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledAutomationController extends Controller
{
    /**
     * Map a scheduled automation for the frontend.
     *
     * @return array<string, mixed>
     */
    private function mapScheduledAutomation(ScheduledAutomation $scheduledAutomation): array
    {
        return [
            'id' => $scheduledAutomation->id,
            'name' => $scheduledAutomation->name,
            'description' => $scheduledAutomation->description,
            'status' => $scheduledAutomation->status,
            'triggerType' => $scheduledAutomation->trigger_type,
            'cronExpression' => $scheduledAutomation->cron_expression,
            'intervalValue' => $scheduledAutomation->interval_value,
            'intervalUnit' => $scheduledAutomation->interval_unit,
            'timezone' => $scheduledAutomation->timezone,
            'nextRunAt' => $scheduledAutomation->next_run_at?->toISOString(),
            'lastRunAt' => $scheduledAutomation->last_run_at?->toISOString(),
            'lastResult' => $scheduledAutomation->last_result,
            'parentAgentId' => $scheduledAutomation->parent_agent_id,
            'parentAgentName' => $scheduledAutomation->parentAgent?->name,
            'childAgentId' => $scheduledAutomation->child_agent_id,
            'childAgentName' => $scheduledAutomation->childAgent?->name,
            'sourceMessageId' => $scheduledAutomation->source_message_id,
            'payload' => $scheduledAutomation->payload ?? [],
            'runsCount' => $scheduledAutomation->runs_count ?? 0,
            'latestRun' => $scheduledAutomation->latestRun?->status,
            'latestApproval' => $scheduledAutomation->latestApproval?->status,
        ];
    }

    public function index(Team $current_team): Response
    {
        $scheduledAutomations = $current_team->scheduledAutomations()
            ->with([
                'parentAgent',
                'childAgent',
                'latestRun',
                'latestApproval',
            ])
            ->withCount('runs')
            ->latest()
            ->get();

        $agents = $current_team->automationAgents()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_agent_id', 'target_tool']);

        return Inertia::render('automation/scheduled-automations', [
            'scheduledAutomations' => $scheduledAutomations->map(fn (ScheduledAutomation $scheduledAutomation): array => $this->mapScheduledAutomation($scheduledAutomation)),
            'selectedScheduledAutomation' => null,
            'mode' => 'list',
            'agents' => $agents->map(fn (AutomationAgent $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'parentAgentId' => $agent->parent_agent_id,
                'targetTool' => $agent->target_tool,
            ]),
        ]);
    }

    public function show(
        Request $request,
        Team $current_team,
        ScheduledAutomation $scheduled_automation,
    ): Response {
        abort_unless($scheduled_automation->team_id === $current_team->id, 404);

        $scheduled_automation->load([
            'parentAgent',
            'childAgent',
            'latestRun',
            'latestApproval',
        ])->loadCount('runs');

        $scheduledAutomations = $current_team->scheduledAutomations()
            ->with(['parentAgent', 'childAgent', 'latestRun', 'latestApproval'])
            ->withCount('runs')
            ->latest()
            ->get();

        $agents = $current_team->automationAgents()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_agent_id', 'target_tool']);

        return Inertia::render('automation/scheduled-automations', [
            'scheduledAutomations' => $scheduledAutomations->map(fn (ScheduledAutomation $item): array => $this->mapScheduledAutomation($item)),
            'selectedScheduledAutomation' => $this->mapScheduledAutomation($scheduled_automation),
            'mode' => $request->string('mode')->toString() ?: 'view',
            'agents' => $agents->map(fn (AutomationAgent $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'parentAgentId' => $agent->parent_agent_id,
                'targetTool' => $agent->target_tool,
            ]),
        ]);
    }

    public function store(
        ScheduledAutomationRequest $request,
        Team $current_team,
    ): RedirectResponse {
        $parentAgentId = $request->validated('parent_agent_id');
        $childAgentId = $request->validated('child_agent_id');

        if ($parentAgentId !== null) {
            $parentExists = $current_team->automationAgents()
                ->whereKey($parentAgentId)
                ->exists();

            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'parent_agent_id' => __('The selected parent agent is invalid.'),
                ]);
            }
        }

        if ($childAgentId !== null) {
            $childExists = $current_team->automationAgents()
                ->whereKey($childAgentId)
                ->exists();

            if (! $childExists) {
                throw ValidationException::withMessages([
                    'child_agent_id' => __('The selected child agent is invalid.'),
                ]);
            }
        }

        $scheduledAutomation = $current_team->scheduledAutomations()->create([
            'source_message_id' => $request->validated('source_message_id'),
            'parent_agent_id' => $parentAgentId,
            'child_agent_id' => $childAgentId,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'trigger_type' => $request->validated('trigger_type'),
            'cron_expression' => $request->validated('cron_expression'),
            'interval_value' => $request->validated('interval_value'),
            'interval_unit' => $request->validated('interval_unit'),
            'timezone' => $request->validated('timezone'),
            'next_run_at' => $request->validated('next_run_at'),
            'payload' => [
                'context' => $request->validated('context'),
            ],
        ]);

        $scheduledAutomation->refreshNextRunAt($scheduledAutomation->next_run_at?->copy() ?? now());

        return to_route('automation.scheduled-automations.index', $current_team);
    }

    public function update(
        ScheduledAutomationRequest $request,
        Team $current_team,
        ScheduledAutomation $scheduled_automation,
    ): RedirectResponse {
        abort_unless($scheduled_automation->team_id === $current_team->id, 404);

        $scheduled_automation->update([
            'source_message_id' => $request->validated('source_message_id'),
            'parent_agent_id' => $request->validated('parent_agent_id'),
            'child_agent_id' => $request->validated('child_agent_id'),
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'trigger_type' => $request->validated('trigger_type'),
            'cron_expression' => $request->validated('cron_expression'),
            'interval_value' => $request->validated('interval_value'),
            'interval_unit' => $request->validated('interval_unit'),
            'timezone' => $request->validated('timezone'),
            'next_run_at' => $request->validated('next_run_at'),
            'payload' => [
                'context' => $request->validated('context'),
            ],
        ]);

        $scheduled_automation->refreshNextRunAt($scheduled_automation->next_run_at?->copy() ?? now());

        return to_route('automation.scheduled-automations.show', [
            'current_team' => $current_team,
            'scheduled_automation' => $scheduled_automation,
        ]);
    }

    public function destroy(
        Team $current_team,
        ScheduledAutomation $scheduled_automation,
    ): RedirectResponse {
        abort_unless($scheduled_automation->team_id === $current_team->id, 404);

        $scheduled_automation->delete();

        return to_route('automation.scheduled-automations.index', $current_team);
    }
}
