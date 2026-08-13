<?php

namespace Database\Seeders;

use App\Models\AutomationAgent;
use App\Models\Team;
use Illuminate\Database\Seeder;

class DianAgentSeeder extends Seeder
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
                'instructions' => 'Inspect the incoming request, decide whether it belongs to billing, tax, or another workflow, and delegate it to the proper child agent.',
                'trigger_keyword' => 'route task',
                'is_enabled' => true,
            ],
        );

        AutomationAgent::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'parent_agent_id' => $parentAgent->id,
                'target_tool' => 'dian_tax_review',
            ],
            [
                'name' => 'DIAN compliance agent',
                'description' => 'Prepares DIAN tax reviews, deadlines and filing checklists.',
                'instructions' => 'Support the accounting team with DIAN tax obligations, exogenous reporting, income tax, retention, and filing checklists. Gather the missing facts, list the next required documents, and summarize the compliance status clearly.',
                'trigger_keyword' => 'dian tax',
                'is_enabled' => true,
            ],
        );

        AutomationAgent::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'parent_agent_id' => $parentAgent->id,
                'target_tool' => 'dian_training',
            ],
            [
                'name' => 'DIAN training curator',
                'description' => 'Captures approved corrections and updates for the DIAN workflow.',
                'instructions' => 'Do not answer final users directly. Capture corrections from the accountant, normalize them into clear rules, FAQs, examples, and checklist items, and prepare suggested updates for review before they are published.',
                'trigger_keyword' => 'dian training',
                'is_enabled' => true,
            ],
        );
    }
}
