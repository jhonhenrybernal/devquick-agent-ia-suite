<?php

namespace App\Services\Telegram;

use App\Actions\Automation\SendTelegramMessage;
use App\Models\AiProviderConfiguration;
use App\Models\AutomationAgent;
use App\Models\DolibarrConfiguration;
use App\Models\Team;
use App\Models\TelegramInboundMessage;
use App\Services\AiProvider\AiProviderApi;
use App\Services\Dolibarr\DolibarrApi;
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
                'No pude leer bien tu mensaje. Escríbeme otra vez y lo reviso.',
                $this->skip('Inbound Telegram message has no chat ID or text.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'missing_chat_or_text',
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
                'Todavía no tengo listo el agente padre y el hijo de facturación. Revisa la configuración de agentes.',
                $this->skip('No billing agent is ready yet.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'agents_not_ready',
                ],
            );
        }

        [$parentAgent, $billingAgent] = $selectedAgents;

        $toolResult = $this->resolveToolAction($team, $inboundMessage, $parentAgent, $billingAgent);

        if ($toolResult !== null) {
            if (! $toolResult['synced']) {
                return $this->sendFallbackReply(
                    $team,
                    $inboundMessage,
                    'Tengo el flujo de facturación identificado, pero Dolibarr todavía no está listo para consultar información real.',
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

        $aiProviderConfiguration = $team->aiProviderConfiguration;

        if (
            ! $aiProviderConfiguration instanceof AiProviderConfiguration
            || ! $aiProviderConfiguration->is_enabled
        ) {
            return $this->sendFallbackReply(
                $team,
                $inboundMessage,
                'Tengo los agentes listos, pero el proveedor IA todavía no está activo. Actívalo para que pueda responder mejor.',
                $this->skip('AI provider is not ready.'),
                [
                    'status' => 'sent',
                    'mode' => 'fallback',
                    'reason' => 'ai_provider_not_ready',
                ],
            );
        }

        $systemPrompt = $this->systemPrompt($parentAgent, $billingAgent);
        $userPrompt = $this->userPrompt($inboundMessage);

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
                'Estoy conectado, pero el modelo IA no devolvió una respuesta útil todavía. Revisa la configuración del proveedor.',
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
            'description' => 'Telegram message processed by the billing agent.',
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'ai',
            'provider' => $reply['provider'] ?? null,
            'model' => $reply['model'] ?? null,
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
        ]);
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
    ): ?array {
        $intent = $this->detectToolIntent($inboundMessage->message_text ?? '');

        if ($intent === null) {
            return null;
        }

        $configuration = $team->dolibarrConfiguration;

        if (! $configuration instanceof DolibarrConfiguration) {
            return [
                'synced' => false,
                'description' => 'Dolibarr no esta configurado para consultar informacion real.',
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
            'description' => sprintf('Telegram message processed with %s.', $intent['tool']),
            'response_text' => $responseText,
        ], [
            'status' => 'sent',
            'mode' => 'tool',
            'tool' => $intent['tool'],
            'search' => $intent['search'] ?? null,
            'parent_agent_id' => $parentAgent->id,
            'agent_id' => $billingAgent->id,
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
            'description' => sprintf('Telegram training message captured as %s.', $trainingIntent['kind']),
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

    private function systemPrompt(AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        return implode("\n\n", [
            'Eres el agente padre central de automatizacion conectado a Telegram.',
            'Responde en espanol, de forma breve, clara y util.',
            'Tu funcion es conversar con la persona, entender la solicitud y coordinar el flujo.',
            'Si faltan datos para facturar, solicita solo la informacion que haga falta.',
            'Cuando la solicitud sea de facturacion, prepara la respuesta y deja listo el contexto para el agente hijo especializado.',
            sprintf('Agente padre: %s. %s', $parentAgent->name, $parentAgent->instructions),
            sprintf('Agente de facturacion: %s. %s', $billingAgent->name, $billingAgent->instructions),
            'Tu objetivo es mantener la conversacion, preparar la factura o confirmar que el flujo de facturacion quedo listo.',
        ]);
    }

    private function userPrompt(TelegramInboundMessage $message): string
    {
        return implode("\n", array_filter([
            sprintf('Mensaje de Telegram: %s', $message->message_text),
            $message->from_username ? sprintf('Usuario: @%s', $message->from_username) : null,
            $message->from_user_id ? sprintf('User ID: %s', $message->from_user_id) : null,
            $message->chat_id ? sprintf('Chat ID: %s', $message->chat_id) : null,
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
                'No encontre facturas recientes en Dolibarr. El agente padre %s y el agente de facturacion %s ya consultaron la tool get_invoices, pero no hay registros para mostrar.',
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
            'Ya consulte Dolibarr con get_invoices y encontre %d facturas recientes:%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
    }

    /**
     * @param  array{count: int, customers: array<int, array<string, mixed>>}  $result
     */
    private function buildCustomersSummary(array $result, AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        if (($result['count'] ?? 0) === 0) {
            return sprintf(
                'No encontre clientes en Dolibarr para mostrar. El agente padre %s y el agente de facturacion %s ya consultaron get_customers.',
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
            'Ya consulte Dolibarr con get_customers y encontre %d clientes:%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
    }

    /**
     * @param  array{count: int, products: array<int, array<string, mixed>>}  $result
     */
    private function buildProductsSummary(array $result, AutomationAgent $parentAgent, AutomationAgent $billingAgent): string
    {
        if (($result['count'] ?? 0) === 0) {
            return sprintf(
                'No encontre productos o servicios en Dolibarr. El agente padre %s y el agente de facturacion %s ya consultaron search_products.',
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
            'Ya consulte Dolibarr con search_products y encontre %d productos o servicios:%s%s',
            (int) ($result['count'] ?? 0),
            PHP_EOL,
            implode(PHP_EOL, $lines),
        );
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
