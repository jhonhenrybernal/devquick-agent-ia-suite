<?php

namespace App\Services\AiProvider;

use App\Enums\AiProvider;
use App\Models\AiProviderConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiProviderApi
{
    /**
     * Validate the configured provider connection.
     *
     * @return array{
     *     valid: bool,
     *     provider: string,
     *     provider_label: string,
     *     description: string,
     *     setup_url: string,
     *     model?: string|null
     * }
     */
    public function testConnection(AiProviderConfiguration $configuration): array
    {
        $provider = $configuration->providerEnum();

        return match ($provider) {
            AiProvider::OpenAi => $this->testOpenAi($configuration),
            AiProvider::Gemini => $this->testGemini($configuration),
            AiProvider::Ollama => $this->testOllama($configuration),
        };
    }

    /**
     * Translate an exception into a friendly message.
     */
    public function describeFailure(AiProviderConfiguration $configuration, Throwable $exception): string
    {
        $provider = $configuration->providerEnum();
        $message = strtolower($exception->getMessage());

        if ($provider === AiProvider::Ollama) {
            return 'Ollama no responde. Verifica que el servicio este instalado y corriendo. Si aun no lo tienes, instalalo desde la pagina oficial.';
        }

        if (str_contains($message, 'unauthorized') || str_contains($message, 'invalid api key')) {
            return $provider === AiProvider::OpenAi
                ? 'La API key de OpenAI no es valida.'
                : 'La API key de Gemini no es valida.';
        }

        return sprintf('No se pudo comunicar con %s.', $provider->label());
    }

    /**
     * Generate a natural-language reply using the configured provider.
     *
     * @return array{
     *     valid: bool,
     *     provider: string,
     *     provider_label: string,
     *     description: string,
     *     setup_url: string,
     *     model?: string|null,
     *     response_text?: string|null
     * }
     */
    public function generateReply(
        AiProviderConfiguration $configuration,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        $provider = $configuration->providerEnum();

        return match ($provider) {
            AiProvider::OpenAi => $this->generateOpenAiReply($configuration, $systemPrompt, $userPrompt),
            AiProvider::Gemini => $this->generateGeminiReply($configuration, $systemPrompt, $userPrompt),
            AiProvider::Ollama => $this->generateOllamaReply($configuration, $systemPrompt, $userPrompt),
        };
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null}
     */
    private function testOpenAi(AiProviderConfiguration $configuration): array
    {
        if (blank($configuration->api_key)) {
            return [
                'valid' => false,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => 'Guarda una API key de OpenAI antes de probar la conexion.',
                'setup_url' => AiProvider::OpenAi->setupUrl(),
            ];
        }

        try {
            $response = Http::baseUrl('https://api.openai.com/v1')
                ->acceptJson()
                ->withToken($configuration->api_key)
                ->timeout(15)
                ->get('/models');
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => $exception->getMessage(),
                'setup_url' => AiProvider::OpenAi->setupUrl(),
            ];
        }

        if ($response->successful()) {
            return [
                'valid' => true,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => 'La conexion con OpenAI respondio correctamente.',
                'setup_url' => AiProvider::OpenAi->setupUrl(),
                'model' => $configuration->model,
            ];
        }

        return [
            'valid' => false,
            'provider' => AiProvider::OpenAi->value,
            'provider_label' => AiProvider::OpenAi->label(),
            'description' => $this->responseDescription($response->json(), $response->body()),
            'setup_url' => AiProvider::OpenAi->setupUrl(),
        ];
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null}
     */
    private function testGemini(AiProviderConfiguration $configuration): array
    {
        if (blank($configuration->api_key)) {
            return [
                'valid' => false,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => 'Guarda una API key de Gemini antes de probar la conexion.',
                'setup_url' => AiProvider::Gemini->setupUrl(),
            ];
        }

        try {
            $response = Http::baseUrl('https://generativelanguage.googleapis.com/v1beta')
                ->acceptJson()
                ->timeout(15)
                ->get('/models', [
                    'key' => $configuration->api_key,
                ]);
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => $exception->getMessage(),
                'setup_url' => AiProvider::Gemini->setupUrl(),
            ];
        }

        if ($response->successful()) {
            return [
                'valid' => true,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => 'La conexion con Gemini respondio correctamente.',
                'setup_url' => AiProvider::Gemini->setupUrl(),
                'model' => $configuration->model,
            ];
        }

        return [
            'valid' => false,
            'provider' => AiProvider::Gemini->value,
            'provider_label' => AiProvider::Gemini->label(),
            'description' => $this->responseDescription($response->json(), $response->body()),
            'setup_url' => AiProvider::Gemini->setupUrl(),
        ];
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null}
     */
    private function testOllama(AiProviderConfiguration $configuration): array
    {
        $baseUrl = rtrim((string) ($configuration->base_url ?: AiProvider::Ollama->defaultBaseUrl()), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(10)
                ->get('/api/version');
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::Ollama->value,
                'provider_label' => AiProvider::Ollama->label(),
                'description' => 'Ollama no esta disponible en esta maquina. Instalalo o arracalo antes de volver a probar.',
                'setup_url' => AiProvider::Ollama->setupUrl(),
            ];
        }

        if ($response->successful()) {
            return [
                'valid' => true,
                'provider' => AiProvider::Ollama->value,
                'provider_label' => AiProvider::Ollama->label(),
                'description' => 'Ollama respondio correctamente en la maquina local.',
                'setup_url' => AiProvider::Ollama->setupUrl(),
                'model' => $configuration->model,
            ];
        }

        return [
            'valid' => false,
            'provider' => AiProvider::Ollama->value,
            'provider_label' => AiProvider::Ollama->label(),
            'description' => $this->responseDescription($response->json(), $response->body()),
            'setup_url' => AiProvider::Ollama->setupUrl(),
        ];
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null, response_text?: string|null}
     */
    private function generateOpenAiReply(
        AiProviderConfiguration $configuration,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        if (blank($configuration->api_key)) {
            return [
                'valid' => false,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => 'Guarda una API key de OpenAI antes de generar respuestas.',
                'setup_url' => AiProvider::OpenAi->setupUrl(),
            ];
        }

        try {
            $response = Http::baseUrl('https://api.openai.com/v1')
                ->acceptJson()
                ->withToken($configuration->api_key)
                ->timeout(30)
                ->post('/chat/completions', [
                    'model' => $configuration->model ?: AiProvider::OpenAi->defaultModel(),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.2,
                ]);
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => $exception->getMessage(),
                'setup_url' => AiProvider::OpenAi->setupUrl(),
            ];
        }

        if (! $response->successful()) {
            return [
                'valid' => false,
                'provider' => AiProvider::OpenAi->value,
                'provider_label' => AiProvider::OpenAi->label(),
                'description' => $this->responseDescription($response->json(), $response->body()),
                'setup_url' => AiProvider::OpenAi->setupUrl(),
            ];
        }

        $responseText = data_get($response->json(), 'choices.0.message.content');

        return [
            'valid' => is_string($responseText) && filled($responseText),
            'provider' => AiProvider::OpenAi->value,
            'provider_label' => AiProvider::OpenAi->label(),
            'description' => is_string($responseText) && filled($responseText)
                ? 'OpenAI respondio correctamente.'
                : 'OpenAI no devolvio texto utilizable.',
            'setup_url' => AiProvider::OpenAi->setupUrl(),
            'model' => $configuration->model ?: AiProvider::OpenAi->defaultModel(),
            'response_text' => is_string($responseText) ? trim($responseText) : null,
        ];
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null, response_text?: string|null}
     */
    private function generateGeminiReply(
        AiProviderConfiguration $configuration,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        if (blank($configuration->api_key)) {
            return [
                'valid' => false,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => 'Guarda una API key de Gemini antes de generar respuestas.',
                'setup_url' => AiProvider::Gemini->setupUrl(),
            ];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => $configuration->api_key,
                ])
                ->timeout(30)
                ->post(sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                    $configuration->model ?: AiProvider::Gemini->defaultModel(),
                ), [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemPrompt],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $userPrompt],
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => $exception->getMessage(),
                'setup_url' => AiProvider::Gemini->setupUrl(),
            ];
        }

        if (! $response->successful()) {
            return [
                'valid' => false,
                'provider' => AiProvider::Gemini->value,
                'provider_label' => AiProvider::Gemini->label(),
                'description' => $this->responseDescription($response->json(), $response->body()),
                'setup_url' => AiProvider::Gemini->setupUrl(),
            ];
        }

        $responseJson = $response->json();
        $responseText = collect(data_get($responseJson, 'candidates', []))
            ->flatMap(function (mixed $candidate): array {
                if (! is_array($candidate)) {
                    return [];
                }

                $parts = data_get($candidate, 'content.parts', []);

                return is_array($parts) ? $parts : [];
            })
            ->pluck('text')
            ->filter(fn (mixed $part): bool => is_string($part) && filled($part))
            ->implode("\n");

        return [
            'valid' => filled($responseText),
            'provider' => AiProvider::Gemini->value,
            'provider_label' => AiProvider::Gemini->label(),
            'description' => filled($responseText)
                ? 'Gemini respondio correctamente.'
                : $this->geminiFailureDescription($responseJson),
            'setup_url' => AiProvider::Gemini->setupUrl(),
            'model' => $configuration->model ?: AiProvider::Gemini->defaultModel(),
            'response_text' => filled($responseText) ? $responseText : null,
            'failure_reason' => filled($responseText)
                ? null
                : $this->geminiFailureReason($responseJson),
        ];
    }

    /**
     * @return array{valid: bool, provider: string, provider_label: string, description: string, setup_url: string, model?: string|null, response_text?: string|null}
     */
    private function generateOllamaReply(
        AiProviderConfiguration $configuration,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        $baseUrl = rtrim((string) ($configuration->base_url ?: AiProvider::Ollama->defaultBaseUrl()), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout(30)
                ->post('/api/chat', [
                    'model' => $configuration->model ?: AiProvider::Ollama->defaultModel(),
                    'stream' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'provider' => AiProvider::Ollama->value,
                'provider_label' => AiProvider::Ollama->label(),
                'description' => 'Ollama no responde. Verifica que el servicio este instalado y corriendo. Si aun no lo tienes, instalalo desde la pagina oficial.',
                'setup_url' => AiProvider::Ollama->setupUrl(),
            ];
        }

        if (! $response->successful()) {
            return [
                'valid' => false,
                'provider' => AiProvider::Ollama->value,
                'provider_label' => AiProvider::Ollama->label(),
                'description' => $this->responseDescription($response->json(), $response->body()),
                'setup_url' => AiProvider::Ollama->setupUrl(),
            ];
        }

        $responseText = data_get($response->json(), 'message.content');

        return [
            'valid' => is_string($responseText) && filled($responseText),
            'provider' => AiProvider::Ollama->value,
            'provider_label' => AiProvider::Ollama->label(),
            'description' => is_string($responseText) && filled($responseText)
                ? 'Ollama respondio correctamente.'
                : 'Ollama no devolvio texto utilizable.',
            'setup_url' => AiProvider::Ollama->setupUrl(),
            'model' => $configuration->model ?: AiProvider::Ollama->defaultModel(),
            'response_text' => is_string($responseText) ? trim($responseText) : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function responseDescription(?array $payload, string $fallback): string
    {
        return (string) ($payload['error']['message'] ?? $payload['message'] ?? $payload['description'] ?? $fallback);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function geminiFailureDescription(?array $payload): string
    {
        if (! is_array($payload)) {
            return 'Gemini devolvio una respuesta vacia.';
        }

        $errorMessage = data_get($payload, 'error.message');

        if (is_string($errorMessage) && filled($errorMessage)) {
            return $errorMessage;
        }

        $blockReason = data_get($payload, 'promptFeedback.blockReason');

        if (is_string($blockReason) && filled($blockReason)) {
            $blockReasonMessage = data_get($payload, 'promptFeedback.blockReasonMessage');

            return filled($blockReasonMessage)
                ? sprintf('Gemini bloqueo la respuesta: %s (%s).', $blockReason, (string) $blockReasonMessage)
                : sprintf('Gemini bloqueo la respuesta: %s.', $blockReason);
        }

        $candidates = data_get($payload, 'candidates', []);

        if (! is_array($candidates) || $candidates === []) {
            return 'Gemini respondio sin candidates.';
        }

        $firstCandidate = $candidates[0] ?? null;

        if (! is_array($firstCandidate)) {
            return 'Gemini respondio con un candidate invalido.';
        }

        $finishReason = data_get($firstCandidate, 'finishReason');

        if (is_string($finishReason) && filled($finishReason)) {
            return sprintf('Gemini respondio sin texto utilizable. finishReason=%s.', $finishReason);
        }

        $parts = data_get($firstCandidate, 'content.parts', []);

        if (! is_array($parts) || $parts === []) {
            return 'Gemini respondio con candidates pero sin content.parts.';
        }

        return 'Gemini respondio con parts sin texto utilizable.';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function geminiFailureReason(?array $payload): string
    {
        if (! is_array($payload)) {
            return 'empty_payload';
        }

        $errorMessage = data_get($payload, 'error.message');

        if (is_string($errorMessage) && filled($errorMessage)) {
            return 'api_error';
        }

        $blockReason = data_get($payload, 'promptFeedback.blockReason');

        if (is_string($blockReason) && filled($blockReason)) {
            return sprintf('blocked_%s', strtolower($blockReason));
        }

        $candidates = data_get($payload, 'candidates', []);

        if (! is_array($candidates) || $candidates === []) {
            return 'missing_candidates';
        }

        $firstCandidate = $candidates[0] ?? null;

        if (! is_array($firstCandidate)) {
            return 'invalid_candidate';
        }

        $finishReason = data_get($firstCandidate, 'finishReason');

        if (is_string($finishReason) && filled($finishReason)) {
            return sprintf('finish_reason_%s', strtolower($finishReason));
        }

        $parts = data_get($firstCandidate, 'content.parts', []);

        if (! is_array($parts) || $parts === []) {
            return 'missing_content_parts';
        }

        return 'missing_text';
    }
}
