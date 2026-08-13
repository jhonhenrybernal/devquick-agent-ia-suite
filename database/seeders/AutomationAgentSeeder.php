<?php

namespace Database\Seeders;

use App\Models\AutomationAgent;
use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AutomationAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Team $team): void
    {
        $parentAgent = AutomationAgent::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'parent_agent_id' => null,
                'target_tool' => 'route_task',
            ],
            [
                'name' => 'Automation orchestrator',
                'description' => 'Routes incoming tasks to the right child agent.',
                'instructions' => 'Inspect the incoming request, decide whether it belongs to billing, and delegate it to the proper child agent.',
                'trigger_keyword' => 'route task',
                'is_enabled' => true,
            ],
        );

        AutomationAgent::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'parent_agent_id' => $parentAgent->id,
                'target_tool' => 'create_invoice',
            ],
            [
                'name' => 'Monthly billing agent',
                'description' => 'Creates monthly customer invoices in Dolibarr.',
                'instructions' => 'Create the monthly invoice, validate the customer, and prepare the payload for Dolibarr using the configured MCP tools.',
                'trigger_keyword' => 'monthly invoice',
                'is_enabled' => true,
            ],
        );
    }
}
