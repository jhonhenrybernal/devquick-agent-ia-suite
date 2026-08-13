<?php

namespace App\Http\Controllers\Automation;

use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\AiProviderConfigurationRequest;
use App\Models\Team;
use App\Services\AiProvider\AiProviderApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AiProviderController extends Controller
{
    /**
     * Show the AI provider configuration page.
     */
    public function edit(Team $current_team): Response
    {
        $configuration = $current_team->aiProviderConfiguration;
        $provider = AiProvider::tryFrom($configuration?->provider ?? '') ?? AiProvider::OpenAi;

        return Inertia::render('automation/ai-provider', [
            'aiProviderConfiguration' => [
                'provider' => $configuration?->provider ?? $provider->value,
                'providerLabel' => $provider->label(),
                'model' => $configuration?->model ?? $provider->defaultModel(),
                'apiKey' => $configuration?->api_key,
                'baseUrl' => $configuration?->base_url ?? $provider->defaultBaseUrl(),
                'isEnabled' => (bool) ($configuration?->is_enabled ?? false),
                'hasApiKey' => filled($configuration?->api_key),
                'isLocal' => $provider->isLocal(),
                'setupUrl' => $provider->setupUrl(),
            ],
            'providerOptions' => AiProvider::options(),
        ]);
    }

    /**
     * Update the AI provider configuration.
     */
    public function update(
        AiProviderConfigurationRequest $request,
        Team $current_team,
    ): RedirectResponse {
        $validated = $request->validated();
        $provider = AiProvider::from($validated['provider']);
        $configuration = $current_team->aiProviderConfiguration()->firstOrNew();

        $configuration->team_id = $current_team->id;
        $configuration->provider = $provider->value;
        $configuration->model = $validated['model'] ?: $provider->defaultModel();

        if ($request->filled('api_key')) {
            $configuration->api_key = $validated['api_key'];
        }

        if ($provider->isLocal()) {
            $configuration->base_url = $validated['base_url'] ?: $provider->defaultBaseUrl();
        } elseif ($request->filled('base_url')) {
            $configuration->base_url = $validated['base_url'];
        } else {
            $configuration->base_url = null;
        }

        $configuration->is_enabled = $request->boolean('is_enabled');
        $configuration->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('AI provider configuration saved.'),
        ]);

        return to_route('automation.ai-provider.edit', $current_team);
    }

    /**
     * Validate the configured provider.
     */
    public function testConnection(
        Team $current_team,
        AiProviderApi $aiProviderApi,
    ): RedirectResponse {
        $configuration = $current_team->aiProviderConfiguration;

        if (! $configuration) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('AI provider configuration not found.'),
            ]);

            return to_route('automation.ai-provider.edit', $current_team);
        }

        try {
            $result = $aiProviderApi->testConnection($configuration);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $aiProviderApi->describeFailure($configuration, $exception),
            ]);

            return to_route('automation.ai-provider.edit', $current_team);
        }

        if (! $result['valid']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['description'],
            ]);

            return to_route('automation.ai-provider.edit', $current_team);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                '%s connection is working with model %s.',
                $result['provider_label'],
                $result['model'] ?? $configuration->model ?? 'default',
            ),
        ]);

        return to_route('automation.ai-provider.edit', $current_team);
    }

    /**
     * Stream a live AI reply for the configured provider.
     */
    public function stream(
        Request $request,
        Team $current_team,
        AiProviderApi $aiProviderApi,
    ): StreamedResponse {
        $configuration = $current_team->aiProviderConfiguration;
        $prompt = trim((string) $request->string(
            'prompt',
            'Responde en espanol con una frase breve y clara confirmando que estas escuchando.',
        ));

        return response()->stream(function () use ($configuration, $aiProviderApi, $prompt): void {
            $write = static function (array $payload): void {
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            if (! $configuration) {
                $write([
                    'type' => 'error',
                    'message' => 'No se encontro configuracion del proveedor IA.',
                ]);

                $write([
                    'type' => 'done',
                ]);

                return;
            }

            $write([
                'type' => 'status',
                'message' => sprintf('Conectando con %s...', $configuration->providerEnum()->label()),
            ]);

            $responseText = '';

            $result = $aiProviderApi->streamReply(
                $configuration,
                'Responde como un asistente de automatizacion de Laravel, claro, breve y en espanol.',
                $prompt,
                function (string $chunk) use (&$responseText, $write): void {
                    $responseText .= $chunk;

                    $write([
                        'type' => 'chunk',
                        'content' => $chunk,
                    ]);
                },
            );

            if (! $result['valid']) {
                $write([
                    'type' => 'error',
                    'message' => $result['description'],
                    'reason' => $result['failure_reason'] ?? null,
                    'provider' => $result['provider'] ?? null,
                    'model' => $result['model'] ?? null,
                    'responseText' => $result['response_text'] ?? $responseText,
                ]);

                $write([
                    'type' => 'done',
                ]);

                return;
            }

            $write([
                'type' => 'done',
                'message' => $result['description'],
                'responseText' => $result['response_text'] ?? $responseText,
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
