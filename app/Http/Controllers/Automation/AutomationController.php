<?php

namespace App\Http\Controllers\Automation;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Dolibarr\DolibarrApi;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
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
}
