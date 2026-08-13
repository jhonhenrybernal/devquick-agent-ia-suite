<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramInboundMessage;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    ): Response {
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
        $updateId = data_get($payload, 'update_id');

        $telegramUpdateId = null;
        $telegramWebhookLockOwner = null;

        if (is_int($updateId) || is_string($updateId)) {
            $telegramUpdateId = (int) $updateId;

            $alreadyProcessed = $current_team->telegramInboundMessages()
                ->where('direction', 'inbound')
                ->where('update_id', $telegramUpdateId)
                ->exists();

            if ($alreadyProcessed) {
                Log::info('Telegram webhook ignored duplicate update.', [
                    'team_id' => $current_team->id,
                    'team_slug' => $current_team->slug,
                    'update_id' => $telegramUpdateId,
                ]);

                return response()->noContent();
            }

            $telegramWebhookLock = Cache::lock(
                $this->webhookLockKey($current_team->id, $telegramUpdateId),
                300,
            );

            if (! $telegramWebhookLock->get()) {
                Log::info('Telegram webhook ignored locked update.', [
                    'team_id' => $current_team->id,
                    'team_slug' => $current_team->slug,
                    'update_id' => $telegramUpdateId,
                ]);

                return response()->noContent();
            }

            $telegramWebhookLockOwner = $telegramWebhookLock->owner();
        }

        $updateType = $this->updateType($payload);
        $chatId = $this->chatIdFromPayload($payload);
        $fromUserId = $this->fromUserId($payload);
        $fromUsername = $this->fromUsername($payload);
        $messageText = $this->messageText($payload);

        Log::info('Telegram webhook received.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'update_id' => $updateId,
            'update_type' => $updateType,
            'chat_id' => $chatId,
            'from_user_id' => $fromUserId,
            'from_username' => $fromUsername,
            'message_text' => $messageText,
        ]);

        try {
            $message = $current_team->telegramInboundMessages()->create([
                'direction' => 'inbound',
                'update_id' => $updateId,
                'update_type' => $updateType,
                'chat_id' => $chatId,
                'from_user_id' => $fromUserId,
                'from_username' => $fromUsername,
                'message_text' => $messageText,
                'payload' => $payload,
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateWebhookMessage($exception)) {
                $this->releaseWebhookLock(
                    $current_team->id,
                    $telegramUpdateId,
                    $telegramWebhookLockOwner,
                );

                Log::info('Telegram webhook ignored duplicate insert.', [
                    'team_id' => $current_team->id,
                    'team_slug' => $current_team->slug,
                    'update_id' => $telegramUpdateId,
                ]);

                return response()->noContent();
            }

            $this->releaseWebhookLock(
                $current_team->id,
                $telegramUpdateId,
                $telegramWebhookLockOwner,
            );

            throw $exception;
        }

        Log::info('Telegram inbound message stored.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'message_id' => $message->id,
            'update_id' => $message->update_id,
            'chat_id' => $message->chat_id,
        ]);

        ProcessTelegramInboundMessage::dispatchAfterResponse(
            $current_team->id,
            $message->id,
            $telegramUpdateId ?? 0,
            $telegramWebhookLockOwner ?? '',
        );

        Log::info('Telegram inbound message queued for processing.', [
            'team_id' => $current_team->id,
            'team_slug' => $current_team->slug,
            'inbound_message_id' => $message->id,
        ]);

        return response()->noContent();
    }

    private function webhookLockKey(int $teamId, int $updateId): string
    {
        return sprintf('telegram:webhook:%d:%d', $teamId, $updateId);
    }

    private function releaseWebhookLock(
        int $teamId,
        ?int $updateId,
        ?string $telegramWebhookLockOwner,
    ): void {
        if ($updateId === null || blank($telegramWebhookLockOwner)) {
            return;
        }

        Cache::restoreLock(
            $this->webhookLockKey($teamId, $updateId),
            $telegramWebhookLockOwner,
        )->release();
    }

    private function isDuplicateWebhookMessage(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'telegram_inbound_messages_team_id_direction_update_id_unique');
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
