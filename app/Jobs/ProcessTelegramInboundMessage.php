<?php

namespace App\Jobs;

use App\Models\Team;
use App\Models\TelegramInboundMessage;
use App\Services\Telegram\TelegramConversationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTelegramInboundMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly int $telegramInboundMessageId,
        public readonly int $telegramUpdateId,
        public readonly string $telegramWebhookLockOwner,
    ) {
        //
    }

    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return $this->teamId.':'.$this->telegramUpdateId;
    }

    public function handle(TelegramConversationSyncService $telegramConversationSyncService): void
    {
        $team = Team::query()->find($this->teamId);

        if (! $team instanceof Team) {
            Log::warning('Telegram inbound job skipped: team not found.', [
                'team_id' => $this->teamId,
                'telegram_inbound_message_id' => $this->telegramInboundMessageId,
            ]);

            return;
        }

        $telegramInboundMessage = TelegramInboundMessage::query()
            ->where('team_id', $team->id)
            ->where('direction', 'inbound')
            ->find($this->telegramInboundMessageId);

        if (! $telegramInboundMessage instanceof TelegramInboundMessage) {
            Log::warning('Telegram inbound job skipped: message not found.', [
                'team_id' => $team->id,
                'telegram_inbound_message_id' => $this->telegramInboundMessageId,
            ]);

            return;
        }

        try {
            $syncResult = $telegramConversationSyncService->handle($team, $telegramInboundMessage);

            Log::info('Telegram AI sync result.', [
                'team_id' => $team->id,
                'team_slug' => $team->slug,
                'inbound_message_id' => $telegramInboundMessage->id,
                'synced' => $syncResult['synced'],
                'description' => $syncResult['description'],
            ]);
        } finally {
            $this->releaseWebhookLock($team->id, $this->telegramUpdateId, $this->telegramWebhookLockOwner);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->releaseWebhookLock($this->teamId, $this->telegramUpdateId, $this->telegramWebhookLockOwner);

        Log::error('Telegram inbound job failed.', [
            'team_id' => $this->teamId,
            'telegram_inbound_message_id' => $this->telegramInboundMessageId,
            'description' => $exception->getMessage(),
        ]);
    }

    private function releaseWebhookLock(int $teamId, int $telegramUpdateId, string $telegramWebhookLockOwner): void
    {
        if (blank($telegramWebhookLockOwner)) {
            return;
        }

        Cache::restoreLock(
            $this->webhookLockKey($teamId, $telegramUpdateId),
            $telegramWebhookLockOwner,
        )->release();
    }

    private function webhookLockKey(int $teamId, int $telegramUpdateId): string
    {
        return sprintf('telegram:webhook:%d:%d', $teamId, $telegramUpdateId);
    }
}
