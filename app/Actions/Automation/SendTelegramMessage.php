<?php

namespace App\Actions\Automation;

use App\Models\TelegramConfiguration;
use App\Notifications\Automation\TelegramMessageNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use InvalidArgumentException;

class SendTelegramMessage
{
    /**
     * Send a Telegram message using the team's configuration.
     */
    public function handle(
        TelegramConfiguration $telegramConfiguration,
        string $content,
        string|int|null $chatId = null,
        array $historyContext = [],
    ): void
    {
        $destinationChatId = $chatId ?? $telegramConfiguration->chat_id;

        if (blank($telegramConfiguration->bot_token) || blank($destinationChatId)) {
            throw new InvalidArgumentException('Telegram configuration is incomplete.');
        }

        (new AnonymousNotifiable)
            ->route('telegram', $destinationChatId)
            ->notifyNow(new TelegramMessageNotification(
                botToken: $telegramConfiguration->bot_token,
                chatId: $destinationChatId,
                content: $content,
            ));

        $team = $telegramConfiguration->team;

        if ($team) {
            $team->telegramInboundMessages()->create([
                'direction' => 'outbound',
                'update_type' => $historyContext['update_type'] ?? 'assistant_message',
                'chat_id' => (string) $destinationChatId,
                'from_user_id' => $historyContext['from_user_id'] ?? null,
                'from_username' => $historyContext['from_username'] ?? null,
                'message_text' => $content,
                'payload' => array_filter([
                    'sent_via' => 'telegram_notification',
                    'history_context' => $historyContext,
                ], static fn (mixed $value): bool => $value !== []),
            ]);
        }
    }
}
