<?php

namespace App\Notifications\Automation;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramMessageNotification extends Notification
{
    /**
     * Create a new Telegram notification.
     */
    public function __construct(
        public readonly string $botToken,
        public readonly string|int $chatId,
        public readonly string $content,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['telegram'];
    }

    /**
     * Build the Telegram message payload.
     */
    public function toTelegram(mixed $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->token($this->botToken)
            ->to($this->chatId)
            ->normal()
            ->content($this->content);
    }
}
