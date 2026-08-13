<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Telegram\TelegramConversationSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Team $current_team,
        TelegramConversationSyncService $telegramConversationSyncService,
    ): Response
    {
        $telegramConfiguration = $current_team->telegramConfiguration;

        if (
            ! $telegramConfiguration
            || ! $telegramConfiguration->is_enabled
            || blank($telegramConfiguration->webhook_secret)
        ) {
            Log::warning('Telegram webhook rejected: configuration missing or inactive.', [
                'team_id' => $current_team->id,
                'team_slug' => $current_team->slug,
                'has_configuration' => (bool) $telegramConfiguration,
                'is_enabled' => (bool) ($telegramConfiguration?->is_enabled ?? false),
                'has_webhook_secret' => filled($telegramConfiguration?->webhook_secret),
            ]);

            abort(404);
        }

        if (! hash_equals(
            (string) $telegramConfiguration->webhook_secret,
            (string) $request->header('X-Telegram-Bot-Api-Secret-Token'),
        )) {
            Log::warning('Telegram webhook rejected: secret token mismatch.', [
                'team_id' => $current_team->id,
                'team_slug' => $current_team->slug,
            ]);

            abort(403);
        }

        $payload = $request->all();
        $updateType = $this->updateType($payload);
        $chatId = $this->chatIdFromPayload($payload);
        $fromUserId = $this->fromUserId($payload);
        $fromUsername = $this->fromUsername($payload);
        $messageText = $this->messageText($payload);

        Log::info('Telegram webhook received.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'update_id' => data_get($payload, 'update_id'),
            'update_type' => $updateType,
            'chat_id' => $chatId,
            'from_user_id' => $fromUserId,
            'from_username' => $fromUsername,
            'message_text' => $messageText,
        ]);

        $message = $current_team->telegramInboundMessages()->create([
            'direction' => 'inbound',
            'update_id' => data_get($payload, 'update_id'),
            'update_type' => $updateType,
            'chat_id' => $chatId,
            'from_user_id' => $fromUserId,
            'from_username' => $fromUsername,
            'message_text' => $messageText,
            'payload' => $payload,
        ]);

        Log::info('Telegram inbound message stored.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'message_id' => $message->id,
            'update_id' => $message->update_id,
            'chat_id' => $message->chat_id,
        ]);

        $syncResult = $telegramConversationSyncService->handle($current_team, $message);

        Log::info('Telegram AI sync result.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'inbound_message_id' => $message->id,
            'synced' => $syncResult['synced'],
            'description' => $syncResult['description'],
        ]);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateType(array $payload): ?string
    {
        foreach ([
            'message',
            'edited_message',
            'channel_post',
            'callback_query',
            'my_chat_member',
            'chat_member',
        ] as $type) {
            if (array_key_exists($type, $payload)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function chatIdFromPayload(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'message.chat.id'),
            data_get($payload, 'edited_message.chat.id'),
            data_get($payload, 'channel_post.chat.id'),
            data_get($payload, 'callback_query.message.chat.id'),
            data_get($payload, 'my_chat_member.chat.id'),
            data_get($payload, 'chat_member.chat.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_int($candidate) || is_string($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fromUserId(array $payload): ?string
    {
        $candidate = data_get($payload, 'message.from.id')
            ?? data_get($payload, 'edited_message.from.id')
            ?? data_get($payload, 'callback_query.from.id');

        return is_int($candidate) || is_string($candidate)
            ? (string) $candidate
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fromUsername(array $payload): ?string
    {
        $candidate = data_get($payload, 'message.from.username')
            ?? data_get($payload, 'edited_message.from.username')
            ?? data_get($payload, 'callback_query.from.username');

        return is_string($candidate) && filled($candidate)
            ? $candidate
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function messageText(array $payload): ?string
    {
        $candidate = data_get($payload, 'message.text')
            ?? data_get($payload, 'edited_message.text')
            ?? data_get($payload, 'callback_query.data');

        return is_string($candidate) && filled($candidate)
            ? $candidate
            : null;
    }
}
