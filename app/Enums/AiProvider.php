<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case Ollama = 'ollama';

    /**
     * Get the human-friendly label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Gemini',
            self::Ollama => 'Ollama',
        };
    }

    /**
     * Get the recommended model for the provider.
     */
    public function defaultModel(): string
    {
        return match ($this) {
            self::OpenAi => 'gpt-4.1-mini',
            self::Gemini => 'gemini-2.5-flash',
            self::Ollama => 'llama3.1',
        };
    }

    /**
     * Get the default base URL for the provider.
     */
    public function defaultBaseUrl(): ?string
    {
        return match ($this) {
            self::Ollama => 'http://localhost:11434',
            default => null,
        };
    }

    /**
     * Get the installation or setup URL for the provider.
     */
    public function setupUrl(): string
    {
        return match ($this) {
            self::OpenAi => 'https://platform.openai.com/docs/quickstart/make-your-first-api-request',
            self::Gemini => 'https://ai.google.dev/gemini-api/docs/get-started',
            self::Ollama => 'https://www.ollama.com/download',
        };
    }

    /**
     * Determine if the provider runs locally.
     */
    public function isLocal(): bool
    {
        return $this === self::Ollama;
    }

    /**
     * Get the available provider options.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $provider): array => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'description' => match ($provider) {
                    self::OpenAi => 'GPT con API de OpenAI.',
                    self::Gemini => 'Modelos de Google Gemini.',
                    self::Ollama => 'Modelo local con Ollama.',
                },
            ],
            self::cases(),
        );
    }
}
