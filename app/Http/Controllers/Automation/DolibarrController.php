<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\DolibarrConfigurationRequest;
use App\Models\Team;
use App\Services\Dolibarr\DolibarrApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DolibarrController extends Controller
{
    /**
     * Show the Dolibarr configuration page.
     */
    public function edit(Team $current_team, DolibarrApi $dolibarrApi): Response
    {
        $configuration = $current_team->dolibarrConfiguration;

        return Inertia::render('automation/dolibarr', [
            'dolibarrConfiguration' => [
                'apiLogin' => old('api_login', $configuration?->api_login),
                'apiPassword' => old('api_password'),
                'apiUrl' => old('api_url', $configuration?->api_url),
                'hasApiLogin' => filled($configuration?->api_login),
                'hasApiPassword' => filled($configuration?->api_password),
                'hasApiUrl' => filled($configuration?->api_url),
                'discoveredApis' => $configuration?->discovered_apis ?? [],
                'discoveredApiCount' => count($configuration?->discovered_apis ?? []),
                'importantApis' => $dolibarrApi->importantApis($configuration?->discovered_apis ?? []),
                'lastDiscoveredAt' => $configuration?->last_discovered_at?->toIso8601String(),
                'setupUrl' => 'https://wiki.dolibarr.org/index.php/Module_Web_Services_API_REST_(developer)',
            ],
        ]);
    }

    /**
     * Update the Dolibarr configuration.
     */
    public function update(
        DolibarrConfigurationRequest $request,
        Team $current_team,
        DolibarrApi $dolibarrApi,
    ): RedirectResponse {
        $configuration = $current_team->dolibarrConfiguration()->firstOrNew();
        $validated = $request->validated();

        $configuration->team_id = $current_team->id;
        $configuration->api_login = $validated['api_login'];
        $configuration->api_password = $validated['api_password'];
        $configuration->api_url = $validated['api_url'];

        try {
            $result = $dolibarrApi->inspectConfiguration($configuration);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $dolibarrApi->describeFailure($exception),
            ]);

            return to_route('automation.dolibarr.edit', $current_team)->withInput(
                Arr::except($validated, 'api_password'),
            );
        }

        if (! $result['valid']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['description'],
            ]);

            return to_route('automation.dolibarr.edit', $current_team)->withInput(
                Arr::except($validated, 'api_password'),
            );
        }

        $configuration->discovered_apis = $result['discovered_apis'];
        $configuration->last_discovered_at = now();
        $configuration->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['description'],
        ]);

        return to_route('automation.dolibarr.edit', $current_team);
    }

    /**
     * Test the Dolibarr connection.
     */
    public function testConnection(
        Team $current_team,
        DolibarrApi $dolibarrApi,
    ): RedirectResponse {
        $configuration = $current_team->dolibarrConfiguration;

        if (! $configuration) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Dolibarr configuration not found.'),
            ]);

            return to_route('automation.dolibarr.edit', $current_team);
        }

        try {
            $result = $dolibarrApi->inspectConfiguration($configuration);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $dolibarrApi->describeFailure($exception),
            ]);

            return to_route('automation.dolibarr.edit', $current_team);
        }

        if (! $result['valid']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['description'],
            ]);

            return to_route('automation.dolibarr.edit', $current_team);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Dolibarr connection is working.'),
        ]);

        return to_route('automation.dolibarr.edit', $current_team);
    }
}
