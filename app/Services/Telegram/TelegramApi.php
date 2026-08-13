<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramApi
{
    /**
     * Validate that the token identifies a working bot.
     *
     * @return array{valid: bool, bot_username?: string|null, bot_name?: string|null, description?: string|null}
     */
    public function validateToken(string $token): array
    {
        try {
            $response = Http::baseUrl($this->baseUri())
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint($token, 'getMe'));
        } catch (ConnectionException $exception) {
            return [
                'valid' => false,
                'description' => $exception->getMessage(),
            ];
        }

        if ($response->successful()) {
            $data = $response->json();

            return [
                'valid' => (bool) ($data['ok'] ?? false),
                'bot_username' => $data['result']['username'] ?? null,
                'bot_name' => $data['result']['first_name'] ?? null,
                'description' => $data['description'] ?? null,
            ];
        }

        return [
            'valid' => false,
            'description' => $this->telegramDescription($response->json(), $response->body()),
        ];
    }

    /**
     * Detect the most recent chat ID available for the bot.
     *
     * @return array{detected: bool, chat_id?: int|string|null, update_type?: string|null, description?: string|null}
     */
    public function detectChatId(string $token): array
    {
        try {
            $response = Http::baseUrl($this->baseUri())
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint($token, 'getUpdates'));
        } catch (ConnectionException $exception) {
            return [
                'detected' => false,
                'description' => $exception->getMessage(),
            ];
        }

        if (! $response->successful()) {
            return [
                'detected' => false,
                'description' => $this->telegramDescription($response->json(), $response->body()),
            ];
        }

        $updates = $response->json('result', []);

        foreach (array_reverse($updates) as $update) {
            $chatId = $this->chatIdFromUpdate($update);

            if ($chatId !== null) {
                return [
                    'detected' => true,
                    'chat_id' => $chatId,
                    'update_type' => $this->updateType($update),
                ];
            }
        }

        return [
            'detected' => false,
            'status' => 'no_updates',
            'description' => 'No hay actualizaciones de Telegram todavia. Abre el bot, envia /start y vuelve a intentar.',
        ];
    }

    /**
     * Register a webhook for the bot.
     *
     * @return array{success: bool, description?: string|null}
     */
    public function setWebhook(string $token, string $url, ?string $secretToken = null): array
    {
        try {
            $request = Http::baseUrl($this->baseUri())
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint($token, 'setWebhook'), array_filter([
                    'url' => $url,
                    'secret_token' => $secretToken,
                ], static fn (mixed $value): bool => filled($value)));
        } catch (ConnectionException $exception) {
            return [
                'success' => false,
                'description' => $exception->getMessage(),
            ];
        }

        if ($request->successful()) {
            $data = $request->json();

            return [
                'success' => (bool) ($data['ok'] ?? false),
                'description' => $data['description'] ?? null,
            ];
        }

        return [
            'success' => false,
            'description' => $this->telegramDescription($request->json(), $request->body()),
        ];
    }

    /**
     * Get the current webhook information for the bot.
     *
     * @return array{
     *     success: bool,
     *     url?: string|null,
     *     has_custom_certificate?: bool|null,
     *     pending_update_count?: int|null,
     *     last_error_date?: int|null,
     *     last_error_message?: string|null,
     *     ip_address?: string|null,
     *     max_connections?: int|null,
     *     allowed_updates?: array<int, string>|null,
     *     description?: string|null
     * }
     */
    public function getWebhookInfo(string $token): array
    {
        try {
            $request = Http::baseUrl($this->baseUri())
                ->acceptJson()
                ->timeout(15)
                ->get($this->endpoint($token, 'getWebhookInfo'));
        } catch (ConnectionException $exception) {
            return [
                'success' => false,
                'description' => $exception->getMessage(),
            ];
        }

        if ($request->successful()) {
            $data = $request->json();
            $result = is_array($data['result'] ?? null) ? $data['result'] : [];

            return [
                'success' => (bool) ($data['ok'] ?? false),
                'url' => $result['url'] ?? null,
                'has_custom_certificate' => $result['has_custom_certificate'] ?? null,
                'pending_update_count' => $result['pending_update_count'] ?? null,
                'last_error_date' => $result['last_error_date'] ?? null,
                'last_error_message' => $result['last_error_message'] ?? null,
                'ip_address' => $result['ip_address'] ?? null,
                'max_connections' => $result['max_connections'] ?? null,
                'allowed_updates' => is_array($result['allowed_updates'] ?? null)
                    ? array_values(array_filter($result['allowed_updates']))
                    : null,
                'description' => $data['description'] ?? null,
            ];
        }

        return [
            'success' => false,
            'description' => $this->telegramDescription($request->json(), $request->body()),
        ];
    }

    /**
     * Remove a webhook for the bot.
     *
     * @return array{success: bool, description?: string|null}
     */
    public function deleteWebhook(string $token): array
    {
        try {
            $request = Http::baseUrl($this->baseUri())
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint($token, 'deleteWebhook'));
        } catch (ConnectionException $exception) {
            return [
                'success' => false,
                'description' => $exception->getMessage(),
            ];
        }

        if ($request->successful()) {
            $data = $request->json();

            return [
                'success' => (bool) ($data['ok'] ?? false),
                'description' => $data['description'] ?? null,
            ];
        }

        return [
            'success' => false,
            'description' => $this->telegramDescription($request->json(), $request->body()),
        ];
    }

    /**
     * Translate a Telegram exception into a friendlier message.
     */
    public function describeSendFailure(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'the bot can\'t send messages to the bot')) {
            return 'Chat ID invalido: estas intentando enviar el mensaje al bot, no a un usuario, grupo o canal real.';
        }

        if (str_contains($message, 'chat not found')) {
            return 'Chat ID invalido: Telegram no encontro ese chat.';
        }

        if (str_contains($message, 'Unauthorized')) {
            return 'Token invalido: revisa que el bot token sea el correcto.';
        }

        return 'Telegram test message could not be sent.';
    }

    private function baseUri(): string
    {
        return rtrim((string) config('services.telegram.base_uri', 'https://api.telegram.org'), '/');
    }

    private function endpoint(string $token, string $method): string
    {
        return sprintf('/bot%s/%s', $token, $method);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function telegramDescription(?array $payload, string $fallback): string
    {
        return (string) ($payload['description'] ?? $payload['error_description'] ?? $fallback);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function chatIdFromUpdate(array $update): int|string|null
    {
        $candidates = [
            data_get($update, 'message.chat.id'),
            data_get($update, 'edited_message.chat.id'),
            data_get($update, 'channel_post.chat.id'),
            data_get($update, 'callback_query.message.chat.id'),
            data_get($update, 'my_chat_member.chat.id'),
            data_get($update, 'chat_member.chat.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_int($candidate) || is_string($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function updateType(array $update): ?string
    {
        foreach ([
            'message',
            'edited_message',
            'channel_post',
            'callback_query',
            'my_chat_member',
            'chat_member',
        ] as $type) {
            if (array_key_exists($type, $update)) {
                return $type;
            }
        }

        return null;
    }
}
