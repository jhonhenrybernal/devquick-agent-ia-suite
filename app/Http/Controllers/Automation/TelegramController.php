<?php

namespace App\Http\Controllers\Automation;

use App\Actions\Automation\SendTelegramMessage;
use App\Enums\AiProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\TelegramConfigurationRequest;
use App\Models\AutomationAgent;
use App\Models\Team;
use App\Models\TelegramAccessSession;
use App\Models\TelegramConfiguration;
use App\Models\TelegramInboundMessage;
use App\Services\Telegram\TelegramApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelegramController extends Controller
{
    /**
     * Map a Telegram inbound message for frontend consumption.
     *
     * @return array<string, mixed>
     */
    private function mapInboundMessage(TelegramInboundMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction ?: 'inbound',
            'updateId' => $message->update_id,
            'updateType' => $message->update_type,
            'chatId' => $message->chat_id,
            'fromUserId' => $message->from_user_id,
            'fromUsername' => $message->from_username,
            'messageText' => $message->message_text,
            'payload' => $message->payload,
            'syncStatus' => data_get($message->payload, 'sync.status'),
            'syncDescription' => data_get($message->payload, 'sync.description'),
            'syncResponseText' => data_get($message->payload, 'sync.response_text'),
            'syncMode' => data_get($message->payload, 'sync.mode'),
            'syncReason' => data_get($message->payload, 'sync.reason'),
            'syncProvider' => data_get($message->payload, 'sync.provider'),
            'syncModel' => data_get($message->payload, 'sync.model'),
            'syncTool' => data_get($message->payload, 'sync.tool'),
            'trainingStatus' => data_get($message->payload, 'sync.training.status'),
            'trainingKind' => data_get($message->payload, 'sync.training.kind'),
            'trainingLabel' => data_get($message->payload, 'sync.training.label'),
            'trainingContent' => data_get($message->payload, 'sync.training.content'),
            'trainingCapturedAt' => data_get($message->payload, 'sync.training.captured_at'),
            'trainingUpdatedAt' => data_get($message->payload, 'sync.training.updated_at'),
            'createdAt' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * Map a Telegram access session for frontend consumption.
     *
     * @return array<string, mixed>
     */
    private function mapAccessSession(TelegramAccessSession $session): array
    {
        return [
            'id' => $session->id,
            'telegramUserId' => $session->telegram_user_id,
            'chatId' => $session->chat_id,
            'telegramUsername' => $session->telegram_username,
            'displayName' => $session->display_name,
            'status' => $session->status,
            'requestedAt' => $session->requested_at?->toISOString(),
            'approvedAt' => $session->approved_at?->toISOString(),
            'revokedAt' => $session->revoked_at?->toISOString(),
            'approvedByUserName' => $session->approvedByUser?->name,
            'lastMessageAt' => $session->last_message_at?->toISOString(),
            'notes' => $session->notes,
        ];
    }

    /**
     * Show the Telegram configuration page.
     */
    public function edit(Team $current_team, TelegramApi $telegramApi): Response
    {
        $telegramConfiguration = $current_team->telegramConfiguration;
        $latestWebhookMessage = $current_team->telegramInboundMessages()
            ->latest()
            ->first();
        $telegramAccessSessions = $current_team->telegramAccessSessions()
            ->with(['approvedByUser'])
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->latest('updated_at')
            ->get();
        $aiProviderConfiguration = $current_team->aiProviderConfiguration;
        $aiProvider = AiProvider::tryFrom($aiProviderConfiguration?->provider ?? '') ?? AiProvider::OpenAi;
        $webhookUrl = $telegramConfiguration?->webhook_secret
            ? route('automation.telegram.webhook', $current_team)
            : null;
        $webhookInfo = filled($telegramConfiguration?->bot_token)
            ? $telegramApi->getWebhookInfo((string) $telegramConfiguration->bot_token)
            : null;
        $registeredWebhookUrl = $webhookInfo['url'] ?? null;

        return Inertia::render('automation/telegram', [
            'telegramConfiguration' => [
                'botUsername' => $telegramConfiguration?->bot_username,
                'chatId' => $telegramConfiguration?->chat_id,
                'botToken' => $telegramConfiguration?->bot_token,
                'webhookSecret' => $telegramConfiguration?->webhook_secret,
                'webhookUrl' => $webhookUrl,
                'registeredWebhookUrl' => $registeredWebhookUrl,
                'webhookMatchesExpectedUrl' => $webhookUrl
                    ? $registeredWebhookUrl === $webhookUrl
                    : false,
                'webhookPendingUpdateCount' => $webhookInfo['pending_update_count'] ?? null,
                'webhookLastErrorMessage' => $webhookInfo['last_error_message'] ?? null,
                'webhookLastErrorDate' => isset($webhookInfo['last_error_date'])
                    ? now()->createFromTimestamp((int) $webhookInfo['last_error_date'])->toISOString()
                    : null,
                'isEnabled' => (bool) ($telegramConfiguration?->is_enabled ?? false),
                'hasToken' => filled($telegramConfiguration?->bot_token),
                'hasWebhookSecret' => filled($telegramConfiguration?->webhook_secret),
                'webhookStatusDescription' => $webhookInfo['description'] ?? null,
                'webhookStatusOk' => (bool) ($webhookInfo['success'] ?? false),
                'latestWebhookMessage' => $latestWebhookMessage
                    ? $this->mapInboundMessage($latestWebhookMessage)
                    : null,
                'accessSessions' => $telegramAccessSessions->map(
                    fn (TelegramAccessSession $session): array => $this->mapAccessSession($session),
                ),
                'accessSummary' => [
                    'total' => $telegramAccessSessions->count(),
                    'pending' => $telegramAccessSessions->where('status', TelegramAccessSession::STATUS_PENDING)->count(),
                    'approved' => $telegramAccessSessions->where('status', TelegramAccessSession::STATUS_APPROVED)->count(),
                    'revoked' => $telegramAccessSessions->where('status', TelegramAccessSession::STATUS_REVOKED)->count(),
                ],
                'aiProvider' => [
                    'provider' => $aiProviderConfiguration?->provider ?? $aiProvider->value,
                    'providerLabel' => $aiProvider->label(),
                    'model' => $aiProviderConfiguration?->model ?? $aiProvider->defaultModel(),
                    'isEnabled' => (bool) ($aiProviderConfiguration?->is_enabled ?? false),
                    'isLocal' => $aiProvider->isLocal(),
                ],
            ],
        ]);
    }

    /**
     * Show the Telegram inbox.
     */
    public function inbox(Request $request, Team $current_team): Response
    {
        $messages = $current_team->telegramInboundMessages()
            ->latest()
            ->limit(50)
            ->get();

        $trainingPendingCount = $messages
            ->filter(fn (TelegramInboundMessage $message): bool => data_get($message->payload, 'sync.mode') === 'training'
                && data_get($message->payload, 'sync.training.status') === 'pending')
            ->count();

        $selectedMessage = $messages->firstWhere('id', $request->integer('message'))
            ?? $messages->first();

        return Inertia::render('automation/telegram-inbox', [
            'messages' => $messages->map(fn (TelegramInboundMessage $message): array => $this->mapInboundMessage($message)),
            'selectedMessage' => $selectedMessage
                ? $this->mapInboundMessage($selectedMessage)
                : null,
            'selectedMessageId' => $selectedMessage?->id,
            'messageCount' => $messages->count(),
            'trainingPendingCount' => $trainingPendingCount,
        ]);
    }

    /**
     * Approve a training candidate and publish it into the DIAN agent instructions.
     */
    public function approveTraining(
        Team $current_team,
        TelegramInboundMessage $telegramInboundMessage,
    ): RedirectResponse {
        abort_unless($telegramInboundMessage->team_id === $current_team->id, 404);

        if (data_get($telegramInboundMessage->payload, 'sync.mode') !== 'training') {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This message is not marked as training.'),
            ]);

            return to_route('automation.telegram.inbox', [
                'current_team' => $current_team,
                'message' => $telegramInboundMessage->id,
            ]);
        }

        $trainingNote = $this->trainingNoteFromMessage($telegramInboundMessage);
        $operationalAgent = $this->operationalDianAgent($current_team);

        if ($operationalAgent instanceof AutomationAgent) {
            $this->appendTrainingNote($operationalAgent, $trainingNote);
        }

        $this->updateTrainingStatus($telegramInboundMessage, 'approved', $trainingNote);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Training item approved and published to the DIAN agent.'),
        ]);

        return to_route('automation.telegram.inbox', [
            'current_team' => $current_team,
            'message' => $telegramInboundMessage->id,
        ]);
    }

    /**
     * Reject a training candidate without publishing it.
     */
    public function rejectTraining(
        Team $current_team,
        TelegramInboundMessage $telegramInboundMessage,
    ): RedirectResponse {
        abort_unless($telegramInboundMessage->team_id === $current_team->id, 404);

        if (data_get($telegramInboundMessage->payload, 'sync.mode') !== 'training') {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This message is not marked as training.'),
            ]);

            return to_route('automation.telegram.inbox', [
                'current_team' => $current_team,
                'message' => $telegramInboundMessage->id,
            ]);
        }

        $this->updateTrainingStatus($telegramInboundMessage, 'rejected');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Training item rejected.'),
        ]);

        return to_route('automation.telegram.inbox', [
            'current_team' => $current_team,
            'message' => $telegramInboundMessage->id,
        ]);
    }

    /**
     * Approve a Telegram access session.
     */
    public function approveAccess(
        Team $current_team,
        TelegramAccessSession $telegramAccessSession,
    ): RedirectResponse {
        abort_unless($telegramAccessSession->team_id === $current_team->id, 404);

        $telegramAccessSession->forceFill([
            'status' => TelegramAccessSession::STATUS_APPROVED,
            'approved_at' => now(),
            'revoked_at' => null,
            'approved_by_user_id' => request()->user()?->id,
            'requested_at' => $telegramAccessSession->requested_at ?? now(),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Telegram access approved for :name.', [
                'name' => $telegramAccessSession->display_name
                    ? sprintf('%s (@%s)', $telegramAccessSession->display_name, $telegramAccessSession->telegram_username ?? $telegramAccessSession->telegram_user_id)
                    : ($telegramAccessSession->telegram_username ?? $telegramAccessSession->telegram_user_id),
            ]),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Revoke a Telegram access session.
     */
    public function revokeAccess(
        Team $current_team,
        TelegramAccessSession $telegramAccessSession,
    ): RedirectResponse {
        abort_unless($telegramAccessSession->team_id === $current_team->id, 404);

        $telegramAccessSession->forceFill([
            'status' => TelegramAccessSession::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => __('Telegram access revoked for :name.', [
                'name' => $telegramAccessSession->display_name
                    ? sprintf('%s (@%s)', $telegramAccessSession->display_name, $telegramAccessSession->telegram_username ?? $telegramAccessSession->telegram_user_id)
                    : ($telegramAccessSession->telegram_username ?? $telegramAccessSession->telegram_user_id),
            ]),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Update the Telegram configuration.
     */
    public function update(
        TelegramConfigurationRequest $request,
        Team $current_team,
        TelegramApi $telegramApi,
    ): RedirectResponse {
        $telegramConfiguration = $current_team->telegramConfiguration()->firstOrNew();
        $validated = $request->validated();

        $telegramConfiguration->team_id = $current_team->id;

        if ($request->filled('bot_token')) {
            $telegramConfiguration->bot_token = $validated['bot_token'];
        }

        $telegramConfiguration->bot_username = $validated['bot_username'] ?? null;
        $telegramConfiguration->chat_id = $validated['chat_id'] ?? null;

        if ($request->filled('webhook_secret')) {
            $telegramConfiguration->webhook_secret = $validated['webhook_secret'];
        }

        if (! $this->isValidWebhookSecret($telegramConfiguration->webhook_secret)) {
            $telegramConfiguration->webhook_secret = $this->generateWebhookSecret();
        }

        $telegramConfiguration->is_enabled = $request->boolean('is_enabled');
        $telegramConfiguration->save();

        $webhookResult = $this->syncWebhookConfiguration(
            $telegramConfiguration,
            $current_team,
            $telegramApi,
        );

        $toast = [
            'type' => 'success',
            'message' => $telegramConfiguration->is_enabled
                ? __('Telegram configuration saved and webhook registered.')
                : __('Telegram configuration saved and webhook removed.'),
        ];

        if (! $webhookResult['success']) {
            $toast = [
                'type' => 'warning',
                'message' => $webhookResult['description']
                    ?? __('Telegram configuration saved, but webhook could not be synchronized.'),
            ];
        }

        Inertia::flash('toast', $toast);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Re-synchronize the Telegram webhook without changing the stored values.
     */
    public function syncWebhook(
        Team $current_team,
        TelegramApi $telegramApi,
    ): RedirectResponse {
        $telegramConfiguration = $current_team->telegramConfiguration;

        if (! $telegramConfiguration || blank($telegramConfiguration->bot_token)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Telegram token not found. Save a token first.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $webhookResult = $this->syncWebhookConfiguration(
            $telegramConfiguration,
            $current_team,
            $telegramApi,
        );

        Inertia::flash('toast', [
            'type' => $webhookResult['success'] ? 'success' : 'warning',
            'message' => $webhookResult['success']
                ? __('Telegram webhook re-synchronized.')
                : ($webhookResult['description'] ?? __('Telegram webhook could not be re-synchronized.')),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Generate a webhook secret using only characters accepted by Telegram.
     */
    private function generateWebhookSecret(int $length = 40): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
        $charactersLength = strlen($characters);
        $secret = '';

        for ($index = 0; $index < $length; $index++) {
            $secret .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $secret;
    }

    /**
     * Determine whether a webhook secret matches Telegram's accepted format.
     */
    private function isValidWebhookSecret(?string $secret): bool
    {
        if (blank($secret)) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $secret);
    }

    /**
     * Register or remove the webhook depending on the current configuration.
     *
     * @return array{success: bool, description?: string|null}
     */
    private function syncWebhookConfiguration(
        TelegramConfiguration $telegramConfiguration,
        Team $current_team,
        TelegramApi $telegramApi,
    ): array {
        return $telegramConfiguration->is_enabled
            ? $telegramApi->setWebhook(
                $telegramConfiguration->bot_token,
                route('automation.telegram.webhook', $current_team),
                $telegramConfiguration->webhook_secret,
            )
            : $telegramApi->deleteWebhook($telegramConfiguration->bot_token);
    }

    /**
     * Validate the Telegram token.
     */
    public function validateToken(
        Team $current_team,
        TelegramApi $telegramApi,
    ): RedirectResponse {
        $telegramConfiguration = $current_team->telegramConfiguration;

        if (! $telegramConfiguration || blank($telegramConfiguration->bot_token)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Telegram token not found. Save a token first.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $result = $telegramApi->validateToken($telegramConfiguration->bot_token);

        if (! $result['valid']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Telegram token is invalid.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $telegramConfiguration->bot_username = $result['bot_username'] ?? $telegramConfiguration->bot_username;
        $telegramConfiguration->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Telegram token is valid.'),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Detect the Telegram chat ID from the bot updates.
     */
    public function detectChatId(
        Team $current_team,
        TelegramApi $telegramApi,
    ): RedirectResponse {
        $telegramConfiguration = $current_team->telegramConfiguration;

        if (! $telegramConfiguration || blank($telegramConfiguration->bot_token)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Telegram token not found. Save a token first.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $result = $telegramApi->detectChatId($telegramConfiguration->bot_token);

        if (! $result['detected']) {
            $toastType = ($result['status'] ?? null) === 'no_updates'
                ? 'warning'
                : 'error';

            Inertia::flash('toast', [
                'type' => $toastType,
                'message' => $result['description'] ?? __('Could not detect a chat ID.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $telegramConfiguration->chat_id = (string) $result['chat_id'];
        $telegramConfiguration->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Chat ID detected and saved: :chatId', [
                'chatId' => $telegramConfiguration->chat_id,
            ]),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    /**
     * Send a test Telegram message.
     */
    public function testConnection(
        Team $current_team,
        SendTelegramMessage $sendTelegramMessage,
        TelegramApi $telegramApi,
    ): RedirectResponse {
        $telegramConfiguration = $current_team->telegramConfiguration;

        if (! $telegramConfiguration) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Telegram configuration not found.'),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        $message = sprintf(
            'Telegram test from %s at %s.',
            $current_team->name,
            now()->format('Y-m-d H:i:s'),
        );

        try {
            $sendTelegramMessage->handle($telegramConfiguration, $message);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $telegramApi->describeSendFailure($exception),
            ]);

            return to_route('automation.telegram.edit', $current_team);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Telegram test message sent.'),
        ]);

        return to_route('automation.telegram.edit', $current_team);
    }

    private function operationalDianAgent(Team $current_team): ?AutomationAgent
    {
        return $current_team->automationAgents()
            ->where('target_tool', 'dian_tax_review')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();
    }

    private function appendTrainingNote(AutomationAgent $agent, string $trainingNote): void
    {
        $instructions = trim((string) $agent->instructions);
        $sectionTitle = 'Aprendizajes aprobados';
        $sectionHeader = sprintf("\n\n--- %s ---\n", $sectionTitle);
        $cleanNote = trim(preg_replace('/\s+/', ' ', $trainingNote) ?: $trainingNote);

        if (str_contains($instructions, $cleanNote)) {
            return;
        }

        if (! str_contains($instructions, $sectionTitle)) {
            $instructions .= $sectionHeader;
        }

        $instructions .= sprintf('- %s', $cleanNote);
        $instructions .= "\n";

        $agent->forceFill([
            'instructions' => $instructions,
        ])->save();
    }

    private function trainingNoteFromMessage(TelegramInboundMessage $message): string
    {
        $label = data_get($message->payload, 'sync.training.label');
        $content = data_get($message->payload, 'sync.training.content') ?: $message->message_text;

        $parts = array_filter([
            $label ? sprintf('%s:', $label) : null,
            is_string($content) ? trim($content) : null,
        ]);

        return trim(implode(' ', $parts));
    }

    private function updateTrainingStatus(
        TelegramInboundMessage $message,
        string $status,
        ?string $note = null,
    ): void {
        $payload = $message->payload;

        data_set($payload, 'sync.training.status', $status);
        data_set($payload, 'sync.training.updated_at', now()->toISOString());

        if ($note !== null) {
            data_set($payload, 'sync.training.note', $note);
        }

        $message->forceFill([
            'payload' => $payload,
        ])->save();
    }
}
