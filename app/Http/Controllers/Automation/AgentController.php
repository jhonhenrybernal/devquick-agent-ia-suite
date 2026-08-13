<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\AutomationAgentRequest;
use App\Models\AutomationAgent;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    /**
     * Map an automation agent for frontend consumption.
     *
     * @return array<string, mixed>
     */
    private function mapAgent(AutomationAgent $agent): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'description' => $agent->description,
            'instructions' => $agent->instructions,
            'triggerKeyword' => $agent->trigger_keyword,
            'targetTool' => $agent->target_tool,
            'isEnabled' => $agent->is_enabled,
            'parentAgentId' => $agent->parent_agent_id,
            'parentAgentName' => $agent->parentAgent?->name,
            'childAgentsCount' => $agent->child_agents_count,
            'createdAt' => $agent->created_at?->toISOString(),
        ];
    }

    /**
     * Display the automation agents page.
     */
    public function index(Team $current_team): Response
    {
        $agents = $current_team->automationAgents()
            ->with(['parentAgent'])
            ->withCount('childAgents')
            ->latest()
            ->get();

        return Inertia::render('automation/agents', [
            'agents' => $agents->map(fn (AutomationAgent $agent): array => $this->mapAgent($agent)),
        ]);
    }

    /**
     * Display a single automation agent.
     */
    public function show(
        Request $request,
        Team $current_team,
        AutomationAgent $automation_agent,
    ): Response {
        abort_unless($automation_agent->team_id === $current_team->id, 404);

        $automation_agent->load(['parentAgent'])->loadCount('childAgents');

        return Inertia::render('automation/agent', [
            'agent' => $this->mapAgent($automation_agent),
            'mode' => $request->string('mode')->toString() ?: 'view',
            'isLocked' => $automation_agent->parent_agent_id === null,
        ]);
    }

    /**
     * Store a new automation agent.
     */
    public function store(
        AutomationAgentRequest $request,
        Team $current_team,
    ): RedirectResponse {
        $parentAgentId = $request->validated('parent_agent_id');

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

        $current_team->automationAgents()->create([
            'parent_agent_id' => $parentAgentId,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'trigger_keyword' => $request->validated('trigger_keyword'),
            'target_tool' => $request->validated('target_tool'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Automation agent created.'),
        ]);

        return to_route('automation.agents.index', $current_team);
    }

    /**
     * Update an existing automation agent.
     */
    public function update(
        AutomationAgentRequest $request,
        Team $current_team,
        AutomationAgent $automation_agent,
    ): RedirectResponse {
        abort_unless($automation_agent->team_id === $current_team->id, 404);
        abort_if($automation_agent->parent_agent_id === null, 403);

        $parentAgentId = $request->validated('parent_agent_id');

        if ($parentAgentId !== null) {
            if ($parentAgentId === $automation_agent->id) {
                throw ValidationException::withMessages([
                    'parent_agent_id' => __('The selected parent agent is invalid.'),
                ]);
            }

            $parentExists = $current_team->automationAgents()
                ->whereKey($parentAgentId)
                ->exists();

            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'parent_agent_id' => __('The selected parent agent is invalid.'),
                ]);
            }
        }

        $automation_agent->update([
            'parent_agent_id' => $parentAgentId,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'trigger_keyword' => $request->validated('trigger_keyword'),
            'target_tool' => $request->validated('target_tool'),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Automation agent updated.'),
        ]);

        return to_route('automation.agents.index', $current_team);
    }

    /**
     * Delete an automation agent.
     */
    public function destroy(
        Team $current_team,
        AutomationAgent $automation_agent,
    ): RedirectResponse {
        abort_unless($automation_agent->team_id === $current_team->id, 404);
        abort_if($automation_agent->parent_agent_id === null, 403);

        $automation_agent->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Automation agent deleted.'),
        ]);

        return to_route('automation.agents.index', $current_team);
    }
}
