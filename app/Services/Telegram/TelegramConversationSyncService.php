<?php

namespace App\Services\Telegram;

use App\Actions\Automation\SendTelegramMessage;
use App\Models\AiProviderConfiguration;
use App\Models\AutomationAgent;
use App\Models\DolibarrConfiguration;
use App\Models\Team;
use App\Models\TelegramAccessSession;
use App\Models\TelegramInboundMessage;
use App\Services\AiProvider\AiProviderApi;
use App\Services\Dolibarr\DolibarrApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramConversationSyncService
{
    public function __construct(
        private readonly AiProviderApi $aiProviderApi,
        private readonly DolibarrApi $dolibarrApi,
        private readonly SendTelegramMessage $sendTelegramMessage,
    ) {}

    /**
     * Process an inbound Telegram message and, when possible, generate a reply.
     *
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    public function handle(Team $team, TelegramInboundMessage $inboundMessage): array
    {
        if (blank($inboundMessage->chat_id) || blank($inboundMessage->message_text)) {
            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                'No pude leer bien tu mensaje. Escribeme otra vez y lo reviso.',
                $this->skip('Inbound Telegram message has no chat ID or text.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'missing_chat_or_text',
                ],
            );
        }

        $accessDecision = $this->resolveTelegramAccess($team, $inboundMessage);

        if (! $accessDecision['allowed']) {
            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                (string) $accessDecision['response_text'],
                $this->skip((string) $accessDecision['description']),
                [
                    'status' => 'sent',
                    'mode' => 'authorization',
                    'reason' => (string) $accessDecision['reason'],
                    'access' => array_filter([
                        'session_id' => $accessDecision['session_id'] ?? null,
                        'status' => $accessDecision['session_status'] ?? null,
                        'telegram_user_id' => $accessDecision['telegram_user_id'] ?? null,
                        'telegram_username' => $accessDecision['telegram_username'] ?? null,
                        'display_name' => $accessDecision['display_name'] ?? null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ],
            );
        }

        $trainingIntent = $this->detectTrainingIntent((string) $inboundMessage->message_text);

        if ($trainingIntent !== null) {
            return $this->handleTrainingIntent($team, $inboundMessage, $trainingIntent);
        }

        $selectedAgents = $this->selectedAgents($team);

        if ($selectedAgents === null) {
            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                'Todavia no tengo listo el flujo de facturacion. Revisa la configuracion de agentes.',
                $this->skip('No billing agent is ready yet.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'agents_not_ready',
                ],
            );
        }

        [$parentAgent, $billingAgent] = $selectedAgents;

        $conversationContext = $this->conversationContext($team, $inboundMessage);
        $toolResult = $this->resolveToolAction(
            $team,
            $inboundMessage,
            $parentAgent,
            $billingAgent,
            $conversationContext,
        );

        if ($toolResult !== null) {
            if (! $toolResult['synced']) {
                return $this->sendFallbackReply(
                    $team,
                    $inboundMessage,
                    'Tengo el flujo de facturacion identificado, pero todavia falta activar la conexion para revisar informacion real.',
                    $toolResult,
                    [
                        'status' => 'sent',
                        'mode' => 'fallback',
                        'reason' => 'dolibarr_not_ready',
                    ],
                );
            }

            return $this->finalizeSyncResult(
                $inboundMessage,
                $toolResult,
                [
                    'status' => $toolResult['synced'] ? 'sent' : 'skipped',
                    'mode' => 'tool',
                ],
            );
        }

        if ($this->shouldSendCapabilitiesIntro($inboundMessage, $conversationContext)) {
            return $this->sendCapabilitiesIntro($team, $inboundMessage, $parentAgent, $billingAgent, $conversationContext);
        }

        $aiProviderConfiguration = $team->aiProviderConfiguration;

        if (
            ! $aiProviderConfiguration instanceof AiProviderConfiguration
            || ! $aiProviderConfiguration->is_enabled
        ) {
            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                'Tengo los agentes listos, pero todavia no esta activo el motor de conversacion. Activarlo para que pueda responder mejor.',
                $this->skip('AI provider is not ready.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'ai_provider_not_ready',
                ],
            );
        }

        $systemPrompt = $this->systemPrompt($parentAgent, $billingAgent, $conversationContext);
        $userPrompt = $this->userPrompt($inboundMessage, $conversationContext);

        $responseText = '';

        $reply = $this->aiProviderApi->streamReply(
            $aiProviderConfiguration,
            $systemPrompt,
            $userPrompt,
            function (string $chunk) use (&$responseText): void {
                $responseText .= $chunk;
            },
        );

        $responseText = trim((string) ($reply['response_text'] ?? $responseText));

        if (! $reply['valid'] || blank($responseText)) {
            Log::warning('Telegram AI reply could not be generated.', [
                'team_id' => $team->id,
                'team_slug' => $team->slug,
                'provider' => $reply['provider'] ?? null,
                'description' => $reply['description'] ?? null,
            ]);

            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                'Estoy conectado, pero el motor de conversacion todavia no devolvio una respuesta util. Revisa la configuracion del proveedor.',
                $this->skip($reply['description'] ?? 'AI provider did not return a response.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => $reply['failure_reason'] ?? 'ai_provider_invalid_response',
                    'provider' => $reply['provider'] ?? null,
                    'model' => $reply['model'] ?? null,
                ],
            );
        }

        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            try {
                $this->sendTelegramMessage->handle(
                    $telegramConfiguration,
                    $responseText,
                    $inboundMessage->chat_id,
                    [
                        'update_type' => 'assistant_message',
                        'from_username' => $parentAgent->name,
                        'generated_by' => [
                            'provider' => $reply['provider'] ?? null,
                            'model' => $reply['model'] ?? null,
                            'parent_agent_id' => $parentAgent->id,
                            'agent_id' => $billingAgent->id,
                            'inbound_message_id' => $inboundMessage->id,
                            'conversation' => $conversationContext,
                        ],
                    ],
                );
            } catch (Throwable $exception) {
                Log::warning('Telegram AI reply could not be delivered.', [
                    'team_id' => $team->id,
                    'team_slug' => $team->slug,
                    'inbound_message_id' => $inboundMessage->id,
                    'chat_id' => $inboundMessage->chat_id,
                    'provider' => $reply['provider'] ?? null,
                    'description' => $exception->getMessage(),
                ]);

                return $this->finalizeSyncResult(
                    $inboundMessage,
                    $this->skip('No se pudo enviar la respuesta a Telegram. Revisa el chat ID y el acceso del bot.'),
                    [
                        'status' => 'telegram_send_failed',
                        'reason' => 'send_failed',
                        'provider' => $reply['provider'] ?? null,
                        'model' => $reply['model'] ?? null,
                        'response_text' => $responseText,
                    ],
                );
            }
        }

        Log::info('Telegram AI reply sent.', [
            'team_id' => $team->id,
            'team_slug' => $team->slug,
            'inbound_message_id' => $inboundMessage->id,
            'chat_id' => $inboundMessage->chat_id,
            'provider' => $reply['provider'] ?? null,
            'agent_id' => $parentAgent->id,
            'billing_agent_id' => $billingAgent->id,
        ]);

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => 'Se proceso el mensaje y se preparo una respuesta para la conversacion.',
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'ai',
            'provider' => $reply['provider'] ?? null,
            'model' => $reply['model'] ?? null,
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
            'context' => $conversationContext,
        ]);
    }

    /**
     * Determine whether the Telegram user can talk to the agent yet.
     *
     * @return array{
     *     allowed: bool,
     *     description: string,
     *     reason: string,
     *     response_text: string,
     *     session_id?: int|null,
     *     session_status?: string|null,
     *     telegram_user_id?: string|null,
     *     telegram_username?: string|null,
     *     display_name?: string|null
     * }
     */
    private function resolveTelegramAccess(
        Team $team,
        TelegramInboundMessage $inboundMessage,
    ): array {
        if (blank($inboundMessage->from_user_id)) {
            return [
                'allowed' => false,
                'description' => 'No pude identificar la cuenta de Telegram para autorizarla.',
                'reason' => 'missing_telegram_user',
                'response_text' => 'No pude identificar tu cuenta de Telegram. Abre el chat privado y vuelve a escribir desde tu usuario personal para poder autorizarlo.',
            ];
        }

        $session = $this->syncTelegramAccessSession($team, $inboundMessage);

        if (! $session->isApproved()) {
            return [
                'allowed' => false,
                'description' => $session->isRevoked()
                    ? 'El acceso de Telegram ya fue revocado en la plataforma.'
                    : 'El acceso de Telegram esta pendiente de aprobacion en la plataforma.',
                'reason' => $session->isRevoked()
                    ? 'telegram_access_revoked'
                    : 'telegram_access_pending',
                'response_text' => $session->isRevoked()
                    ? 'Tu acceso desde Telegram fue revocado en la plataforma. Pide a un administrador que lo reactive.'
                    : 'Tu acceso desde Telegram quedo pendiente de aprobacion en la plataforma. Un administrador debe autorizar tu cuenta antes de continuar.',
                'session_id' => $session->id,
                'session_status' => $session->status,
                'telegram_user_id' => $session->telegram_user_id,
                'telegram_username' => $session->telegram_username,
                'display_name' => $session->display_name,
            ];
        }

        return [
            'allowed' => true,
            'description' => 'Acceso autorizado.',
            'reason' => 'telegram_access_approved',
            'response_text' => '',
            'session_id' => $session->id,
            'session_status' => $session->status,
            'telegram_user_id' => $session->telegram_user_id,
            'telegram_username' => $session->telegram_username,
            'display_name' => $session->display_name,
        ];
    }

    private function syncTelegramAccessSession(
        Team $team,
        TelegramInboundMessage $inboundMessage,
    ): TelegramAccessSession {
        $session = $team->telegramAccessSessions()
            ->firstOrNew([
                'telegram_user_id' => (string) $inboundMessage->from_user_id,
            ]);

        $session->forceFill([
            'team_id' => $team->id,
            'chat_id' => $inboundMessage->chat_id,
            'telegram_username' => $inboundMessage->from_username,
            'display_name' => $this->telegramDisplayName($inboundMessage),
            'requested_at' => $session->requested_at ?? now(),
            'last_message_at' => now(),
            'status' => $session->exists ? $session->status : TelegramAccessSession::STATUS_PENDING,
        ]);

        $session->save();

        return $session;
    }

    private function telegramDisplayName(TelegramInboundMessage $inboundMessage): ?string
    {
        $firstName = data_get($inboundMessage->payload, 'message.from.first_name')
            ?? data_get($inboundMessage->payload, 'edited_message.from.first_name')
            ?? data_get($inboundMessage->payload, 'callback_query.from.first_name');
        $lastName = data_get($inboundMessage->payload, 'message.from.last_name')
            ?? data_get($inboundMessage->payload, 'edited_message.from.last_name')
            ?? data_get($inboundMessage->payload, 'callback_query.from.last_name');

        $displayName = trim(implode(' ', array_filter([
            is_string($firstName) ? $firstName : null,
            is_string($lastName) ? $lastName : null,
        ])));

        return $displayName !== '' ? $displayName : null;
    }

    /**
     * Try to answer with a real Dolibarr tool before falling back to chat.
     *
     * @return array{synced: bool, description: string, response_text?: string|null}|null
     */
    private function resolveToolAction(
        Team $team,
        TelegramInboundMessage $inboundMessage,
        AutomationAgent $parentAgent,
        AutomationAgent $billingAgent,
        array $conversationContext,
    ): ?array {
        $invoiceDetailIntent = $this->detectInvoiceDetailIntent(
            (string) ($inboundMessage->message_text ?? ''),
            $conversationContext,
        );

        if ($invoiceDetailIntent !== null) {
            return $this->resolveInvoiceDetailAction(
                $team,
                $inboundMessage,
                $parentAgent,
                $billingAgent,
                $invoiceDetailIntent['reference'],
                $conversationContext,
            );
        }

        $intent = $this->detectToolIntent($inboundMessage->message_text ?? '');

        if ($intent === null) {
            return null;
        }

        $configuration = $team->dolibarrConfiguration;

        if (! $configuration instanceof DolibarrConfiguration) {
            return [
                'synced' => false,
                'description' => 'La conexion para revisar informacion real todavia no esta configurada.',
                'response_text' => null,
            ];
        }

        $result = match ($intent['tool']) {
            'get_invoices' => $this->dolibarrApi->invoices(
                $configuration,
                (string) ($intent['search'] ?? ''),
                (int) ($intent['limit'] ?? 5),
            ),
            'get_customers' => $this->dolibarrApi->customers(
                $configuration,
                (string) ($intent['search'] ?? ''),
                (int) ($intent['limit'] ?? 5),
            ),
            'search_products' => $this->dolibarrApi->searchProducts(
                $configuration,
                (string) ($intent['search'] ?? ''),
                (int) ($intent['limit'] ?? 5),
            ),
        };

        $responseText = match ($intent['tool']) {
            'get_invoices' => $this->buildInvoicesSummary($result, $parentAgent, $billingAgent),
            'get_customers' => $this->buildCustomersSummary($result, $parentAgent, $billingAgent),
            'search_products' => $this->buildProductsSummary($result, $parentAgent, $billingAgent),
        };

        $toolContext = match ($intent['tool']) {
            'get_invoices' => array_filter([
                'topic' => 'invoices',
                'last_invoice_reference' => (string) data_get($result, 'invoices.0.ref'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'get_customers' => array_filter([
                'topic' => 'customers',
                'last_customer_reference' => (string) data_get($result, 'customers.0.reference'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'search_products' => array_filter([
                'topic' => 'products',
                'last_product_reference' => (string) data_get($result, 'products.0.ref'),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        };

        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            $this->sendTelegramMessage->handle(
                $telegramConfiguration,
                $responseText,
                $inboundMessage->chat_id,
                [
                    'update_type' => 'assistant_message',
                    'from_username' => $parentAgent->name,
                    'generated_by' => [
                        'tool' => $intent['tool'],
                        'search' => $intent['search'] ?? null,
                        'parent_agent_id' => $parentAgent->id,
                        'agent_id' => $billingAgent->id,
                        'conversation' => $conversationContext,
                    ],
                ],
            );
        }

        Log::info('Telegram Dolibarr tool reply sent.', [
            'team_id' => $team->id,
            'team_slug' => $team->slug,
            'tool' => $intent['tool'],
            'search' => $intent['search'] ?? null,
            'parent_agent_id' => $parentAgent->id,
            'billing_agent_id' => $billingAgent->id,
        ]);

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => 'Se reviso la informacion solicitada y se devolvio un resumen.',
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'tool',
            'tool' => $intent['tool'],
            'search' => $intent['search'] ?? null,
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
            'context' => array_filter([
                ...$conversationContext,
                ...$toolContext,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }

    /**
     * Resolve a detail request for a previously mentioned invoice reference.
     *
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    private function resolveInvoiceDetailAction(
        Team $team,
        TelegramInboundMessage $inboundMessage,
        AutomationAgent $parentAgent,
        AutomationAgent $billingAgent,
        string $reference,
        array $conversationContext,
    ): array {
        $configuration = $team->dolibarrConfiguration;

        if (! $configuration instanceof DolibarrConfiguration) {
            return [
                'synced' => false,
                'description' => 'La conexion para revisar facturas todavia no esta configurada.',
                'response_text' => null,
            ];
        }

        $reference = trim($reference);
        $invoiceResult = $this->findInvoiceByReference($configuration, $reference);

        if ($invoiceResult === null) {
            return [
                'synced' => false,
                'description' => sprintf('No encontre una factura con la referencia %s.', $reference),
                'response_text' => null,
            ];
        }

        $responseText = $this->buildInvoiceDetailSummary(
            $invoiceResult,
            $reference,
            $parentAgent,
            $billingAgent,
        );

        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            $this->sendTelegramMessage->handle(
                $telegramConfiguration,
                $responseText,
                $inboundMessage->chat_id,
                [
                    'update_type' => 'assistant_message',
                    'from_username' => $parentAgent->name,
                    'generated_by' => [
                        'tool' => 'get_invoice_detail',
                        'reference' => $reference,
                        'parent_agent_id' => $parentAgent->id,
                        'agent_id' => $billingAgent->id,
                        'conversation' => $conversationContext,
                    ],
                ],
            );
        }

        Log::info('Telegram Dolibarr invoice detail reply sent.', [
            'team_id' => $team->id,
            'team_slug' => $team->slug,
            'reference' => $reference,
            'parent_agent_id' => $parentAgent->id,
            'billing_agent_id' => $billingAgent->id,
        ]);

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => sprintf('Se reviso la factura %s y se devolvio su detalle.', $reference),
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'tool',
            'tool' => 'get_invoice_detail',
            'search' => $reference,
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
            'context' => array_filter([
                ...$conversationContext,
                'topic' => 'invoice_detail',
                'last_invoice_reference' => $reference,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }

    /**
     * @return array{0: AutomationAgent, 1: AutomationAgent}|null
     */
    private function selectedAgents(Team $team): ?array
    {
        $parentAgent = $team->automationAgents()
            ->whereNull('parent_agent_id')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        if (! $parentAgent instanceof AutomationAgent) {
            return null;
        }

        $billingAgent = $team->automationAgents()
            ->where('parent_agent_id', $parentAgent->id)
            ->where('is_enabled', true)
            ->where('target_tool', 'create_invoice')
            ->orderBy('id')
            ->first()
            ?? $team->automationAgents()
                ->where('parent_agent_id', $parentAgent->id)
                ->where('is_enabled', true)
                ->orderBy('id')
                ->first();

        if (! $billingAgent instanceof AutomationAgent) {
            return null;
        }

        return [$parentAgent, $billingAgent];
    }

    /**
     * @return array{kind: string, label: string, content: string}|null
     */
    private function detectTrainingIntent(string $messageText): ?array
    {
        $trimmedMessage = trim($messageText);

        if ($trimmedMessage === '') {
            return null;
        }

        if (! preg_match('/^\s*#(?P<tag>regla|correccion|ejemplo|train|aprendizaje)\b[\s:,-]*(?P<content>.*)$/iu', $trimmedMessage, $matches)) {
            return null;
        }

        $tag = mb_strtolower((string) ($matches['tag'] ?? ''));
        $content = trim((string) ($matches['content'] ?? ''));

        return [
            'kind' => match ($tag) {
                'regla' => 'rule',
                'correccion' => 'correction',
                'ejemplo' => 'example',
                'train' => 'training',
                'aprendizaje' => 'learning',
                default => 'training',
            },
            'label' => match ($tag) {
                'regla' => 'Regla',
                'correccion' => 'Correccion',
                'ejemplo' => 'Ejemplo',
                'train' => 'Entrenamiento',
                'aprendizaje' => 'Aprendizaje',
                default => 'Entrenamiento',
            },
            'content' => $content !== '' ? $content : trim($trimmedMessage),
        ];
    }

    /**
     * @param  array{kind: string, label: string, content: string}  $trainingIntent
     */
    private function handleTrainingIntent(
        Team $team,
        TelegramInboundMessage $inboundMessage,
        array $trainingIntent,
    ): array {
        $trainingAgent = $team->automationAgents()
            ->where('target_tool', 'dian_training')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        $operationalAgent = $team->automationAgents()
            ->where('target_tool', 'dian_tax_review')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        $responseText = sprintf(
            'Recibido como %s. Lo dejare en la bandeja de entrenamiento para revisarlo.',
            mb_strtolower($trainingIntent['label']),
        );

        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            try {
                $this->sendTelegramMessage->handle(
                    $telegramConfiguration,
                    $responseText,
                    $inboundMessage->chat_id,
                    [
                        'update_type' => 'assistant_message',
                        'from_username' => $trainingAgent?->name ?? 'DevQuick Assistant',
                        'generated_by' => [
                            'mode' => 'training',
                            'kind' => $trainingIntent['kind'],
                            'label' => $trainingIntent['label'],
                            'inbound_message_id' => $inboundMessage->id,
                            'training_agent_id' => $trainingAgent?->id,
                            'operational_agent_id' => $operationalAgent?->id,
                        ],
                    ],
                );
            } catch (Throwable $exception) {
                Log::warning('Telegram training acknowledgement could not be delivered.', [
                    'team_id' => $team->id,
                    'team_slug' => $team->slug,
                    'inbound_message_id' => $inboundMessage->id,
                    'chat_id' => $inboundMessage->chat_id,
                    'description' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Telegram training message stored.', [
            'team_id' => $team->id,
            'team_slug' => $team->slug,
            'inbound_message_id' => $inboundMessage->id,
            'training_kind' => $trainingIntent['kind'],
            'training_label' => $trainingIntent['label'],
        ]);

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => 'Se guardo el mensaje como referencia para entrenamiento.',
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'training',
            'reason' => sprintf('training_%s', $trainingIntent['kind']),
            'training' => [
                'status' => 'pending',
                'kind' => $trainingIntent['kind'],
                'label' => $trainingIntent['label'],
                'content' => $trainingIntent['content'],
                'captured_at' => now()->toISOString(),
                'source' => 'telegram',
            ],
        ]);
    }

    private function systemPrompt(
        AutomationAgent $parentAgent,
        AutomationAgent $billingAgent,
        array $conversationContext,
    ): string {
        return implode("\n\n", [
            'Eres el asistente principal de la Suite de Quick CRM.',
            'Responde en espanol, con tono natural, coherente y util. Evita saludos repetitivos y respuestas genericas.',
            'Tu funcion es continuar la conversacion actual, entender la solicitud y coordinar el flujo sin mencionar herramientas internas, tecnicismos ni nombres de sistemas.',
            'Usa el contexto reciente para mantener el hilo; si el usuario responde con palabras cortas como "si", "ok", "dale" o "continua", asume que sigue el ultimo tema.',
            'Si faltan datos para resolver una solicitud, pide solo lo que falta y ofrece el siguiente paso concreto.',
            'Cuando la solicitud sea de facturacion, prepara la respuesta y deja listo el contexto para el agente especializado.',
            'Si el usuario pide detalles de una factura, prioriza el numero, cliente, estado, total, fecha y lineas antes de abrir otro tema.',
            sprintf('Agente principal: %s. %s', $parentAgent->name, $parentAgent->instructions),
            sprintf('Agente de facturacion: %s. %s', $billingAgent->name, $billingAgent->instructions),
            'Habla como una interfaz de negocio: di que revisaste, validaste, encontraste o preparaste informacion, pero no digas nombres como Telegram, Dolibarr, MCP, get_invoices, get_customers ni search_products.',
            'Tu objetivo es mantener una conversacion fluida, resolver la solicitud sin reiniciar el hilo y dar respuestas completas pero concretas.',
            $this->conversationContextLine($conversationContext),
        ]);
    }

    private function userPrompt(TelegramInboundMessage $message, array $conversationContext): string
    {
        return implode("\n", array_filter([
            sprintf('Mensaje recibido: %s', $message->message_text),
            $message->from_username ? sprintf('Usuario: @%s', $message->from_username) : null,
            $message->from_user_id ? sprintf('User ID: %s', $message->from_user_id) : null,
            $message->chat_id ? sprintf('Chat ID: %s', $message->chat_id) : null,
            $this->conversationHistoryPrompt($conversationContext),
        ]));
    }

    /**
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    private function skip(string $description): array
    {
        return [
            'synced' => false,
            'description' => $description,
            'response_text' => null,
        ];
    }

    /**
     * Send a short fallback reply to Telegram and store the sync result.
     *
     * @param  array{synced: bool, description: string, response_text?: string|null}  $result
     * @param  array<string, mixed>  $metadata
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    private function sendFallbackReply(
        Team $team,
        TelegramInboundMessage $inboundMessage,
        string $fallbackText,
        array $result,
        array $metadata = [],
    ): array {
        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            try {
                $this->sendTelegramMessage->handle(
                    $telegramConfiguration,
                    $fallbackText,
                    $inboundMessage->chat_id,
                    [
                        'update_type' => 'assistant_message',
                        'from_username' => 'DevQuick Assistant',
                        'generated_by' => [
                            'mode' => 'fallback',
                            'inbound_message_id' => $inboundMessage->id,
                            'reason' => $metadata['reason'] ?? null,
                            'provider' => $metadata['provider'] ?? null,
                            'model' => $metadata['model'] ?? null,
                        ],
                    ],
                );
            } catch (Throwable $exception) {
                Log::warning('Telegram fallback reply could not be delivered.', [
                    'team_id' => $team->id,
                    'team_slug' => $team->slug,
                    'inbound_message_id' => $inboundMessage->id,
                    'chat_id' => $inboundMessage->chat_id,
                    'description' => $exception->getMessage(),
                ]);

                return $this->finalizeSyncResult($inboundMessage, $this->skip('No se pudo enviar una respuesta de respaldo a Telegram.'), [
                    'status' => 'telegram_send_failed',
                    'reason' => 'fallback_send_failed',
                    'response_text' => null,
                ]);
            }
        }

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => $result['description'],
            'response_text' => $fallbackText,
        ], array_filter([
            'status' => $metadata['status'] ?? 'sent',
            'mode' => $metadata['mode'] ?? 'fallback',
            'reason' => $metadata['reason'] ?? null,
            'provider' => $metadata['provider'] ?? null,
            'model' => $metadata['model'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * Persist the sync result inside the inbound message payload for visibility in the inbox.
     *
     * @param  array{synced: bool, description: string, response_text?: string|null}  $result
     * @param  array<string, mixed>  $metadata
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    private function finalizeSyncResult(
        TelegramInboundMessage $inboundMessage,
        array $result,
        array $metadata = [],
    ): array {
        $payload = $inboundMessage->payload;
        $payload['sync'] = array_filter([
            'synced' => $result['synced'],
            'description' => $result['description'],
            'response_text' => $result['response_text'] ?? null,
            ...$metadata,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $inboundMessage->forceFill([
            'payload' => $payload,
        ])->save();

        return $result;
    }

    /**
     * @return array{tool: string, search?: string, limit?: int}|null
     */
    private function detectToolIntent(string $messageText): ?array
    {
        $normalized = mb_strtolower(trim($messageText));

        if ($normalized === '') {
            return null;
        }

        if (
            str_contains($normalized, 'factur')
            || str_contains($normalized, 'resumen')
            || str_contains($normalized, 'histor')
            || str_contains($normalized, 'ultim')
            || str_contains($normalized, 'ultim')
        ) {
            return [
                'tool' => 'get_invoices',
                'limit' => 5,
            ];
        }

        if (
            str_contains($normalized, 'cliente')
            || str_contains($normalized, 'clientes')
            || str_contains($normalized, 'tercer')
        ) {
            return [
                'tool' => 'get_customers',
                'limit' => 5,
                'search' => $this->extractSearchTerm($normalized, ['cliente', 'clientes', 'terceros']),
            ];
        }

        if (
            str_contains($normalized, 'producto')
            || str_contains($normalized, 'productos')
            || str_contains($normalized, 'servicio')
            || str_contains($normalized, 'servicios')
        ) {
            return [
                'tool' => 'search_products',
                'limit' => 5,
                'search' => $this->extractSearchTerm($normalized, ['producto', 'productos', 'servicio', 'servicios']),
            ];
        }

        return null;
    }

    /**
     * @param  array{last_topic?: string|null, last_invoice_reference?: string|null, recent_messages?: array<int, array{role: string, text: string}>}  $conversationContext
     * @return array{reference: string}|null
     */
    private function detectInvoiceDetailIntent(string $messageText, array $conversationContext): ?array
    {
        $normalized = mb_strtolower(trim($messageText));
        $explicitReference = $this->extractInvoiceReference($messageText);

        if ($explicitReference !== null) {
            return [
                'reference' => $explicitReference,
            ];
        }

        $lastTopic = (string) ($conversationContext['last_topic'] ?? '');
        $lastReference = (string) ($conversationContext['last_invoice_reference'] ?? '');

        if ($lastReference === '' || ! in_array($lastTopic, ['invoices', 'invoice_detail'], true)) {
            return null;
        }

        if (! $this->isInvoiceFollowUpMessage($normalized)) {
            return null;
        }

        return [
            'reference' => $lastReference,
        ];
    }

    /**
     * @param  array{last_topic?: string|null, last_invoice_reference?: string|null, recent_messages?: array<int, array{role: string, text: string}>}  $conversationContext
     * @return array{last_topic: string|null, last_invoice_reference: string|null, recent_messages: array<int, array{role: string, text: string}>}
     */
    private function conversationContext(Team $team, TelegramInboundMessage $inboundMessage): array
    {
        $query = $team->telegramInboundMessages()
            ->where('chat_id', $inboundMessage->chat_id)
            ->where('id', '<', $inboundMessage->id)
            ->latest('id');

        if (filled($inboundMessage->from_user_id)) {
            $query->where(function ($builder) use ($inboundMessage): void {
                $builder->where('direction', 'outbound')
                    ->orWhere('from_user_id', $inboundMessage->from_user_id);
            });
        }

        $recentMessages = $query
            ->limit(8)
            ->get()
            ->reverse()
            ->values();

        $recentTurns = [];
        $lastContext = [
            'last_topic' => null,
            'last_invoice_reference' => null,
        ];

        foreach ($recentMessages as $message) {
            $role = $message->direction === 'outbound' ? 'assistant' : 'user';
            $text = trim((string) $message->message_text);

            if ($text !== '') {
                $recentTurns[] = [
                    'role' => $role,
                    'text' => $text,
                ];
            }

            $context = data_get($message->payload, 'sync.context');

            if (is_array($context)) {
                $lastContext = [
                    'last_topic' => is_string($context['topic'] ?? null) ? $context['topic'] : $lastContext['last_topic'],
                    'last_invoice_reference' => is_string($context['last_invoice_reference'] ?? null)
                        ? $context['last_invoice_reference']
                        : $lastContext['last_invoice_reference'],
                ];
            }
        }

        return [
            'last_topic' => $lastContext['last_topic'],
            'last_invoice_reference' => $lastContext['last_invoice_reference'],
            'recent_messages' => $recentTurns,
        ];
    }

    /**
     * @param  array{last_topic?: string|null, last_invoice_reference?: string|null, recent_messages?: array<int, array{role: string, text: string}>}  $conversationContext
     */
    private function conversationContextLine(array $conversationContext): string
    {
        $parts = array_filter([
            filled($conversationContext['last_topic'] ?? null)
                ? sprintf('Tema previo: %s.', (string) $conversationContext['last_topic'])
                : null,
            filled($conversationContext['last_invoice_reference'] ?? null)
                ? sprintf('Factura previa: %s.', (string) $conversationContext['last_invoice_reference'])
                : null,
        ]);

        return $parts !== [] ? implode(' ', $parts) : 'No hay contexto previo relevante.';
    }

    /**
     * @param  array{recent_messages?: array<int, array{role: string, text: string}>}  $conversationContext
     */
    private function conversationHistoryPrompt(array $conversationContext): string
    {
        $recentMessages = $conversationContext['recent_messages'] ?? [];

        if (! is_array($recentMessages) || $recentMessages === []) {
            return 'Contexto reciente: sin mensajes previos relevantes.';
        }

        $lines = [];

        foreach ($recentMessages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? 'user');
            $text = trim((string) ($message['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $lines[] = sprintf(
                '%s: %s',
                $role === 'assistant' ? 'Asistente' : 'Usuario',
                $text,
            );
        }

        return $lines !== []
            ? "Contexto reciente:\n".implode("\n", $lines)
            : 'Contexto reciente: sin mensajes previos relevantes.';
    }

    private function shouldSendCapabilitiesIntro(
        TelegramInboundMessage $inboundMessage,
        array $conversationContext,
    ): bool {
        $recentMessages = $conversationContext['recent_messages'] ?? [];

        if (! is_array($recentMessages) || $recentMessages !== []) {
            return false;
        }

        $normalized = mb_strtolower(trim((string) $inboundMessage->message_text));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^(hola|buenas|buenos dias|buenos días|buenas tardes|buenas noches)[.!?]*$/u', $normalized) === 1) {
            return true;
        }

        return $normalized === 'que puedes hacer'
            || $normalized === 'qué puedes hacer'
            || $normalized === 'que haces'
            || $normalized === 'qué haces'
            || $normalized === 'capacidades'
            || $normalized === 'ayuda'
            || $normalized === 'help';
    }

    /**
     * @return array{synced: bool, description: string, response_text?: string|null}
     */
    private function sendCapabilitiesIntro(
        Team $team,
        TelegramInboundMessage $inboundMessage,
        AutomationAgent $parentAgent,
        AutomationAgent $billingAgent,
        array $conversationContext,
    ): array {
        $responseText = implode("\n", array_filter([
            'Hola. Soy el asistente de Suite de Quick CRM.',
            'Puedo ayudarte con facturacion, consultas sobre clientes, revision de productos o servicios y seguimiento de solicitudes administrativas.',
            'En facturacion puedo mostrar resúmenes, abrir detalles de facturas y preparar el contexto para continuar una conversacion.',
            'Si necesitas algo, dime qué quieres revisar y lo seguimos desde aqui.',
        ]));

        $telegramConfiguration = $team->telegramConfiguration;

        if ($telegramConfiguration) {
            try {
                $this->sendTelegramMessage->handle(
                    $telegramConfiguration,
                    $responseText,
                    $inboundMessage->chat_id,
                    [
                        'update_type' => 'assistant_message',
                        'from_username' => $parentAgent->name,
                        'generated_by' => [
                            'mode' => 'intro',
                            'parent_agent_id' => $parentAgent->id,
                            'agent_id' => $billingAgent->id,
                            'inbound_message_id' => $inboundMessage->id,
                            'conversation' => $conversationContext,
                        ],
                    ],
                );
            } catch (Throwable $exception) {
                Log::warning('Telegram capabilities intro could not be delivered.', [
                    'team_id' => $team->id,
                    'team_slug' => $team->slug,
                    'inbound_message_id' => $inboundMessage->id,
                    'chat_id' => $inboundMessage->chat_id,
                    'description' => $exception->getMessage(),
                ]);

                return $this->finalizeSyncResult($inboundMessage, $this->skip('No se pudo enviar la presentacion inicial a Telegram.'), [
                    'status' => 'telegram_send_failed',
                    'mode' => 'intro',
                    'reason' => 'intro_send_failed',
                    'response_text' => null,
                ]);
            }
        }

        return $this->finalizeSyncResult($inboundMessage, [
            'synced' => true,
            'description' => 'Se envio la presentacion inicial del asistente.',
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'intro',
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
            'context' => $conversationContext,
        ]);
    }

    private function isInvoiceFollowUpMessage(string $messageText): bool
    {
        $normalized = trim(mb_strtolower($messageText));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, [
            'si',
            'ok',
            'dale',
            'continua',
            'continuar',
            'sigue',
            'seguir',
            'ver mas',
            'mas',
            'detalle',
            'detalles',
        ], true) || str_contains($normalized, 'descripcion') || str_contains($normalized, 'detalle') || str_contains($normalized, 'mas info');
    }

    private function extractInvoiceReference(string $messageText): ?string
    {
        if (preg_match('/\b[A-Z]{1,5}\d{4}-\d{4}\b/u', $messageText, $matches)) {
            return (string) $matches[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array{invoice: array<string, mixed>, lines: array<int, array<string, mixed>>, line_count: int}|null
     */
    private function findInvoiceByReference(DolibarrConfiguration $configuration, string $reference): ?array
    {
        $searchResult = $this->dolibarrApi->searchInvoices($configuration, [
            'search' => $reference,
            'limit' => 10,
        ]);

        $invoice = collect($searchResult['invoices'] ?? [])
            ->first(function (array $candidate) use ($reference): bool {
                return mb_strtolower(trim((string) ($candidate['ref'] ?? ''))) === mb_strtolower(trim($reference));
            });

        if (! is_array($invoice) || ! isset($invoice['id'])) {
            return null;
        }

        return $this->dolibarrApi->invoice($configuration, (int) $invoice['id']);
    }

    /**
     * @param  array{invoice: array<string, mixed>, lines: array<int, array<string, mixed>>, line_count: int}  $invoiceResult
     */
    private function buildInvoiceDetailSummary(
        array $invoiceResult,
        string $reference,
        AutomationAgent $parentAgent,
        AutomationAgent $billingAgent,
    ): string {
        $invoice = $invoiceResult['invoice'];
        $lines = array_slice($invoiceResult['lines'] ?? [], 0, 5);
        $lineCount = (int) ($invoiceResult['line_count'] ?? 0);

        $summaryLines = [];

        foreach ($lines as $line) {
            $summaryLines[] = sprintf(
                '- %s x%s | %s',
                trim((string) ($line['description'] ?? 'Sin descripcion')),
                (string) ($line['quantity'] ?? '1'),
                $this->formatMoney($line['totalTtc'] ?? $line['totalHt'] ?? $line['unitPrice'] ?? null),
            );
        }

        $status = $this->invoiceStatusText($invoice['status'] ?? null, $invoice['statusLabel'] ?? null);

        return implode("\n", array_filter([
            sprintf('Te comparto el detalle de la factura %s:', $reference),
            sprintf('Cliente: %s', (string) ($invoice['customerName'] ?? 'Sin cliente')),
            sprintf('Estado: %s', $status),
            sprintf('Fecha: %s', $this->formatDate((string) ($invoice['date'] ?? ''))),
            sprintf('Total: %s', $this->formatMoney($invoice['totalTtc'] ?? $invoice['totalHt'] ?? null)),
            $lineCount > 0 ? sprintf('Lineas (%d):', $lineCount) : null,
            ...$summaryLines,
            sprintf(
                'Si quieres, tambien te puedo dar el cliente, el estado, el total o el detalle completo de esta misma factura.',
            ),
        ]));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function extractSearchTerm(string $messageText, array $keywords): string
    {
        $term = $messageText;

        foreach ($keywords as $keyword) {
            $term = str_replace($keyword, '', $term);
        }

        $term = preg_replace('/\b(mostrar|muestre|muestrame|busca|buscar|resumen|lista|listar|de|del|la|el|las|los|por|favor|puedes|puede|quiero|necesito)\b/u', ' ', $term) ?? $term;
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? $term);

        return $term;
    }

    /**
     * @param  array{count: int, invoices: array<int, array<string, mixed>>}  $result
     */
    private function buildInvoicesSummary(array $result, AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        if (($result['count'] ?? 0) === 0) {
            return sprintf(
                'No encontre facturas recientes en la Suite de Quick CRM. Ya revise el historial de facturacion, pero no hay registros para mostrar.',
                $parentAgent->name,
                $billingAgent->name,
            );
        }

        $lines = [];

        foreach (array_slice($result['invoices'] ?? [], 0, 5) as $invoice) {
            $lines[] = sprintf(
                '- %s | Cliente: %s | Estado: %s | Total: %s',
                (string) ($invoice['ref'] ?? 'Sin referencia'),
                (string) ($invoice['customerName'] ?? 'Sin cliente'),
                (string) ($invoice['statusLabel'] ?? $invoice['status'] ?? 'Sin estado'),
                $this->formatMoney($invoice['totalTtc'] ?? $invoice['total_ht'] ?? null),
            );
        }

        return sprintf(
            'Ya revise tus facturas en la Suite de Quick CRM y encontre %d registros recientes:%s%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
            PHP_EOL.'Si quieres, puedo abrir el detalle de una factura especifica si me dices la referencia.',
        );
    }

    /**
     * @param  array{count: int, customers: array<int, array<string, mixed>>}  $result
     */
    private function buildCustomersSummary(array $result, AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        if (($result['count'] ?? 0) === 0) {
            return sprintf(
                'No encontre clientes para mostrar en la Suite de Quick CRM. Ya revise el directorio de clientes, pero no hay registros para mostrar.',
                $parentAgent->name,
                $billingAgent->name,
            );
        }

        $lines = [];

        foreach (array_slice($result['customers'] ?? [], 0, 5) as $customer) {
            $lines[] = sprintf(
                '- %s | Ref: %s | Ciudad: %s',
                (string) ($customer['name'] ?? 'Sin nombre'),
                (string) ($customer['reference'] ?? 'Sin referencia'),
                (string) ($customer['city'] ?? 'Sin ciudad'),
            );
        }

        return sprintf(
            'Ya revise tus clientes en la Suite de Quick CRM y encontre %d registros:%s%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
            PHP_EOL.'Si necesitas, te puedo ayudar a buscar uno especifico por nombre o referencia.',
        );
    }

    /**
     * @param  array{count: int, products: array<int, array<string, mixed>>}  $result
     */
    private function buildProductsSummary(array $result, AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        if (($result['count'] ?? 0) === 0) {
            return sprintf(
                'No encontre productos o servicios para mostrar en la Suite de Quick CRM. Ya revise el catalogo, pero no hay registros para mostrar.',
                $parentAgent->name,
                $billingAgent->name,
            );
        }

        $lines = [];

        foreach (array_slice($result['products'] ?? [], 0, 5) as $product) {
            $lines[] = sprintf(
                '- %s | Ref: %s | Precio: %s',
                (string) ($product['label'] ?? 'Sin nombre'),
                (string) ($product['ref'] ?? 'Sin referencia'),
                $this->formatMoney($product['priceTtc'] ?? $product['price'] ?? null),
            );
        }

        return sprintf(
            'Ya revise los productos y servicios en la Suite de Quick CRM y encontre %d registros:%s%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
            PHP_EOL.'Si quieres, te ayudo a encontrar un producto especifico para la factura.',
        );
    }

    private function invoiceStatusText(mixed $status, mixed $statusLabel): string
    {
        if (is_string($statusLabel) && filled($statusLabel)) {
            return $statusLabel;
        }

        if (is_int($status) || is_string($status)) {
            return match ((int) $status) {
                0 => 'Borrador',
                1 => 'Validada',
                2 => 'Pagada',
                3 => 'Anulada',
                default => 'Sin estado claro',
            };
        }

        return 'Sin estado claro';
    }

    private function formatDate(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 'Sin fecha';
        }

        try {
            return Carbon::parse($trimmed)->format('Y-m-d');
        } catch (Throwable) {
            return $trimmed;
        }
    }

    private function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin valor';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.');
        }

        return (string) $value;
    }
}
