<?php

namespace App\Http\Controllers\Automation;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\AutomationAgent;
use App\Models\Team;
use App\Services\Dolibarr\DolibarrApi;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    /**
     * Map an automation agent for the overview pages.
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
     * Display the automation overview.
     */
    public function index(Team $current_team, DolibarrApi $dolibarrApi): Response
    {
        $dolibarrConfiguration = $current_team->dolibarrConfiguration;
        $telegramConfiguration = $current_team->telegramConfiguration;
        $aiProviderConfiguration = $current_team->aiProviderConfiguration;
        $aiProvider = AiProvider::tryFrom($aiProviderConfiguration?->provider ?? '') ?? AiProvider::OpenAi;
        $agents = $current_team->automationAgents();

        return Inertia::render('automation/index', [
            'telegram' => [
                'isEnabled' => (bool) ($telegramConfiguration?->is_enabled ?? false),
                'botUsername' => $telegramConfiguration?->bot_username,
                'chatId' => $telegramConfiguration?->chat_id,
                'hasToken' => filled($telegramConfiguration?->bot_token),
                'hasWebhookSecret' => filled($telegramConfiguration?->webhook_secret),
            ],
            'dolibarr' => [
                'hasApiLogin' => filled($dolibarrConfiguration?->api_login),
                'hasApiPassword' => filled($dolibarrConfiguration?->api_password),
                'hasApiUrl' => filled($dolibarrConfiguration?->api_url),
                'discoveredApiCount' => count($dolibarrConfiguration?->discovered_apis ?? []),
                'importantApiCount' => count($dolibarrApi->importantApis($dolibarrConfiguration?->discovered_apis ?? [])),
                'setupUrl' => 'https://wiki.dolibarr.org/index.php/Module_Web_Services_API_REST_(developer)',
            ],
            'agents' => [
                'total' => $agents->count(),
                'enabled' => $agents->where('is_enabled', true)->count(),
            ],
            'aiProvider' => [
                'provider' => $aiProviderConfiguration?->provider ?? $aiProvider->value,
                'providerLabel' => $aiProvider->label(),
                'model' => $aiProviderConfiguration?->model ?? $aiProvider->defaultModel(),
                'isEnabled' => (bool) ($aiProviderConfiguration?->is_enabled ?? false),
                'hasApiKey' => filled($aiProviderConfiguration?->api_key),
                'setupUrl' => $aiProvider->setupUrl(),
                'isLocal' => $aiProvider->isLocal(),
            ],
        ]);
    }

    /**
     * Display the DIAN automation workspace.
     */
    public function dian(Team $current_team): Response
    {
        $agents = $current_team->automationAgents()
            ->with(['parentAgent'])
            ->withCount('childAgents')
            ->whereIn('target_tool', [
                'route_task',
                'dian_tax_review',
                'dian_training',
            ])
            ->orderByRaw('case when parent_agent_id is null then 0 else 1 end')
            ->orderBy('id')
            ->get();

        $parentAgent = $agents->firstWhere('target_tool', 'route_task');
        $operationalAgent = $agents->firstWhere('target_tool', 'dian_tax_review');
        $trainingAgent = $agents->firstWhere('target_tool', 'dian_training');

        return Inertia::render('automation/dian', [
            'parentAgent' => $parentAgent instanceof AutomationAgent
                ? $this->mapAgent($parentAgent)
                : null,
            'operationalAgent' => $operationalAgent instanceof AutomationAgent
                ? $this->mapAgent($operationalAgent)
                : null,
            'trainingAgent' => $trainingAgent instanceof AutomationAgent
                ? $this->mapAgent($trainingAgent)
                : null,
            'agentCount' => $agents->count(),
            'readyCount' => $agents->where('is_enabled', true)->count(),
            'checklist' => [
                'Capturar correcciones del contador en Telegram o en la pagina interna.',
                'Normalizar la regla en un resumen claro y aprobado.',
                'Publicar la instruccion en el agente operativo solo cuando ya este validada.',
            ],
        ]);
    }
}
