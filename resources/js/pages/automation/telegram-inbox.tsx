import { router, Head, Link } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle, MessageSquareText, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import {
    approveTraining as approveTelegramTraining,
    rejectTraining as rejectTelegramTraining,
} from '@/actions/App/Http/Controllers/Automation/TelegramController';
import {
    edit as telegramEdit,
    inbox as telegramInbox,
} from '@/actions/App/Http/Controllers/Automation/TelegramController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { TelegramInboundMessage, Team } from '@/types';

type Props = {
    messages: TelegramInboundMessage[];
    selectedMessage: TelegramInboundMessage | null;
    selectedMessageId?: number | null;
    messageCount: number;
    trainingPendingCount: number;
    currentTeam?: Team | null;
};

function formatDate(value?: string | null): string {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPayload(payload: Record<string, unknown>): string {
    return JSON.stringify(payload, null, 2);
}

function formatSyncReason(reason?: string | null): string {
    if (!reason) {
        return 'Sin motivo';
    }

    const normalizedReason = reason.replace(/_/g, ' ');

    if (reason.startsWith('blocked_')) {
        return `Bloqueado por ${normalizedReason.replace('blocked ', '')}`;
    }

    if (reason.startsWith('finish_reason_')) {
        return `Finalizado por ${normalizedReason.replace('finish reason ', '')}`;
    }

    const friendlyReasons: Record<string, string> = {
        api_error: 'Error del API',
        missing_candidates: 'Sin candidatos en la respuesta',
        invalid_candidate: 'Candidato inválido',
        missing_content_parts: 'Sin content parts',
        missing_text: 'Sin texto utilizable',
        empty_payload: 'Respuesta vacía',
        ai_provider_invalid_response: 'Respuesta no utilizable',
        ai_provider_not_ready: 'Proveedor IA no listo',
        dolibarr_not_ready: 'Dolibarr no listo',
        send_failed: 'Error al enviar',
        fallback_send_failed: 'Error al enviar respaldo',
        training_rule: 'Entrenamiento: regla',
        training_correction: 'Entrenamiento: correccion',
        training_example: 'Entrenamiento: ejemplo',
        training_learning: 'Entrenamiento: aprendizaje',
        telegram_access_pending: 'Acceso pendiente',
        telegram_access_revoked: 'Acceso revocado',
        telegram_access_approved: 'Acceso aprobado',
        missing_telegram_user: 'Cuenta de Telegram no identificada',
    };

    return friendlyReasons[reason] ?? normalizedReason;
}

function formatTrainingStatus(status?: string | null): string {
    if (!status) {
        return 'Sin estado';
    }

    const statuses: Record<string, string> = {
        pending: 'Pendiente',
        approved: 'Aprobado',
        rejected: 'Rechazado',
    };

    return statuses[status] ?? status;
}

export default function TelegramInbox({
    messages,
    selectedMessage,
    selectedMessageId,
    messageCount,
    trainingPendingCount,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            router.reload({
                only: ['messages', 'selectedMessage', 'selectedMessageId', 'messageCount', 'trainingPendingCount'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 5000);

        return () => {
            window.clearInterval(interval);
        };
    }, []);

    return (
        <>
            <Head title="Telegram inbox" />

            <h1 className="sr-only">Telegram inbox</h1>

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">
                            Automation
                        </p>
                        <h2 className="text-2xl font-semibold">Inbox de Telegram</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Aqui ves los mensajes que llegaron al webhook y el detalle completo de cada update.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <Link href={teamSlug ? telegramEdit.url(teamSlug) : '#'}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver a Telegram
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => {
                            router.reload({
                                    only: ['messages', 'selectedMessage', 'selectedMessageId', 'messageCount', 'trainingPendingCount'],
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Refrescar inbox
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-[1fr_1.1fr]">
                    <section className="rounded-lg border bg-background">
                        <div className="border-b px-4 py-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-lg font-semibold">Mensajes recibidos</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {messageCount} mensajes guardados
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {trainingPendingCount} candidatos de entrenamiento pendientes
                                    </p>
                                </div>
                                <Badge variant="secondary" className="rounded-full">
                                    <MessageSquareText className="mr-1 h-3 w-3" />
                                    Telegram
                                </Badge>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border text-left text-sm">
                                <thead className="bg-muted/30">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Mensaje</th>
                                        <th className="px-4 py-3 font-medium">Origen</th>
                                        <th className="px-4 py-3 font-medium">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {messages.length > 0 ? (
                                        messages.map((message) => {
                                            const active = selectedMessageId === message.id;

                                            return (
                                                <tr
                                                    key={message.id}
                                                    className={active ? 'bg-muted/40' : ''}
                                                >
                                                    <td className="px-4 py-4">
                                                        <div className="space-y-1">
                                                            <Link
                                                                href={telegramInbox.url({
                                                                    current_team: teamSlug,
                                                                }, {
                                                                    query: {
                                                                        message: message.id,
                                                                    },
                                                                })}
                                                                className="font-medium hover:underline"
                                                            >
                                                                {message.messageText || 'Sin texto'}
                                                            </Link>
                                                            <div className="flex flex-wrap gap-2">
                                                                <Badge variant="outline" className="rounded-full">
                                                                    {message.updateType || 'update'}
                                                                </Badge>
                                                                {message.syncMode === 'training' ? (
                                                                    <Badge variant="default" className="rounded-full bg-emerald-600 text-primary-foreground">
                                                                        Entrenamiento
                                                                    </Badge>
                                                                ) : null}
                                                                <Badge
                                                                    variant={message.direction === 'outbound' ? 'default' : 'secondary'}
                                                                    className="rounded-full"
                                                                >
                                                                    {message.direction === 'outbound' ? 'Salida' : 'Entrada'}
                                                                </Badge>
                                                                {message.chatId ? (
                                                                    <Badge variant="secondary" className="rounded-full">
                                                                        chat {message.chatId}
                                                                    </Badge>
                                                                ) : null}
                                                                {message.syncReason ? (
                                                                    <Badge variant="outline" className="rounded-full border-amber-500/30 bg-amber-500/10 text-amber-700">
                                                                        {formatSyncReason(message.syncReason)}
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {message.direction === 'outbound'
                                                            ? 'Agente IA'
                                                            : message.fromUsername
                                                            ? `@${message.fromUsername}`
                                                            : message.fromUserId || 'Desconocido'}
                                                    </td>
                                                    <td className="px-4 py-4 text-muted-foreground">
                                                        {formatDate(message.createdAt)}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-4 py-10 text-center text-sm text-muted-foreground"
                                            >
                                                Todavia no han llegado mensajes desde Telegram.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl">Detalle del mensaje</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Selecciona un mensaje de la izquierda para ver su contenido completo.
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {selectedMessage ? (
                                    <>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="rounded-md border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Update ID
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {selectedMessage.updateId ?? 'Sin update'}
                                                </p>
                                            </div>
                                            <div className="rounded-md border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Tipo
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {selectedMessage.updateType ?? 'Sin tipo'}
                                                </p>
                                            </div>
                                            <div className="rounded-md border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Chat ID
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {selectedMessage.chatId ?? 'Sin chat'}
                                                </p>
                                            </div>
                                            <div className="rounded-md border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Usuario
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {selectedMessage.direction === 'outbound'
                                                        ? 'Agente IA'
                                                        : selectedMessage.fromUsername
                                                        ? `@${selectedMessage.fromUsername}`
                                                        : selectedMessage.fromUserId ?? 'Sin usuario'}
                                                </p>
                                            </div>
                                            <div className="rounded-md border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Direccion
                                                </p>
                                                <p className="mt-1 font-medium">
                                                    {selectedMessage.direction === 'outbound'
                                                        ? 'Respuesta del agente'
                                                        : 'Mensaje entrante'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-md border bg-background p-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Estado de sincronizacion
                                                </p>
                                                <Badge
                                                    variant={selectedMessage.syncStatus === 'sent'
                                                        ? 'default'
                                                        : selectedMessage.syncStatus
                                                            ? 'secondary'
                                                            : 'outline'}
                                                    className="rounded-full"
                                                >
                                                    {selectedMessage.syncStatus === 'sent'
                                                        ? 'Enviado'
                                                        : selectedMessage.syncStatus === 'telegram_send_failed'
                                                            ? 'Error de envio'
                                                            : selectedMessage.syncStatus === 'skipped'
                                                                ? 'Omitido'
                                                                : 'Sin estado'}
                                                </Badge>
                                            </div>

                                            {selectedMessage.syncDescription ? (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {selectedMessage.syncDescription}
                                                </p>
                                            ) : (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    Todavia no hay resultado de sincronizacion guardado para este mensaje.
                                                </p>
                                            )}

                                            {(selectedMessage.syncMode ||
                                                selectedMessage.syncTool ||
                                                selectedMessage.syncProvider ||
                                                selectedMessage.syncModel ||
                                                selectedMessage.syncReason ||
                                                selectedMessage.trainingStatus) ? (
                                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                    {selectedMessage.syncMode ? (
                                                        <div className="rounded border bg-muted/10 p-2 text-sm">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Modo
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {selectedMessage.syncMode}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedMessage.syncTool ? (
                                                        <div className="rounded border bg-muted/10 p-2 text-sm">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Tool
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {selectedMessage.syncTool}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedMessage.syncProvider ? (
                                                        <div className="rounded border bg-muted/10 p-2 text-sm">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Proveedor
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {selectedMessage.syncProvider}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedMessage.syncModel ? (
                                                        <div className="rounded border bg-muted/10 p-2 text-sm">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Modelo
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {selectedMessage.syncModel}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedMessage.syncReason ? (
                                                        <div className="rounded border border-amber-500/20 bg-amber-500/5 p-3 text-sm sm:col-span-2">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Motivo
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {formatSyncReason(selectedMessage.syncReason)}
                                                            </p>
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {selectedMessage.syncReason}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                    {selectedMessage.trainingStatus ? (
                                                        <div className="rounded border bg-muted/10 p-2 text-sm">
                                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                                Entrenamiento
                                                            </p>
                                                            <p className="mt-1 font-medium">
                                                                {formatTrainingStatus(selectedMessage.trainingStatus)}
                                                            </p>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="rounded-md border bg-background p-3">
                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                Mensaje
                                            </p>
                                            <p className="mt-2 text-sm whitespace-pre-wrap">
                                                {selectedMessage.messageText || 'Sin texto'}
                                            </p>
                                        </div>

                                        {selectedMessage.syncMode === 'training' ? (
                                            <div className="rounded-md border border-emerald-500/20 bg-emerald-500/5 p-3">
                                                <div className="flex flex-wrap items-center justify-between gap-3">
                                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                        Entrenamiento
                                                    </p>
                                                    <Badge
                                                        variant={selectedMessage.trainingStatus === 'approved'
                                                            ? 'default'
                                                            : selectedMessage.trainingStatus === 'rejected'
                                                                ? 'secondary'
                                                                : 'outline'}
                                                        className="rounded-full"
                                                    >
                                                        {formatTrainingStatus(selectedMessage.trainingStatus)}
                                                    </Badge>
                                                </div>

                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    {selectedMessage.trainingLabel
                                                        ? `${selectedMessage.trainingLabel}.`
                                                        : 'Este mensaje fue marcado para entrenamiento.'}
                                                </p>

                                                {selectedMessage.trainingContent ? (
                                                    <div className="mt-3 rounded border bg-background p-3 text-sm whitespace-pre-wrap">
                                                        {selectedMessage.trainingContent}
                                                    </div>
                                                ) : null}

                                                {selectedMessage.trainingStatus === 'pending' ? (
                                                    <div className="mt-3 flex flex-wrap gap-2">
                                                        <Button
                                                            type="button"
                                                            onClick={() => {
                                                                router.patch(
                                                                    approveTelegramTraining.url({
                                                                        current_team: teamSlug,
                                                                        telegram_inbound_message: selectedMessage.id,
                                                                    }),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            Aprobar y publicar
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => {
                                                                router.patch(
                                                                    rejectTelegramTraining.url({
                                                                        current_team: teamSlug,
                                                                        telegram_inbound_message: selectedMessage.id,
                                                                    }),
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                        >
                                                            Rechazar
                                                        </Button>
                                                    </div>
                                                ) : null}
                                            </div>
                                        ) : null}

                                        <div className="rounded-md border bg-background p-3">
                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                Payload crudo
                                            </p>
                                            <pre className="mt-2 overflow-auto text-xs whitespace-pre-wrap">
                                                {formatPayload(selectedMessage.payload)}
                                            </pre>
                                        </div>

                                        {selectedMessage.syncResponseText ? (
                                            <div className="rounded-md border border-sky-500/20 bg-sky-500/5 p-3">
                                                <p className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    <Sparkles className="h-4 w-4" />
                                                    Respuesta generada
                                                </p>
                                                <p className="mt-2 text-sm whitespace-pre-wrap">
                                                    {selectedMessage.syncResponseText}
                                                </p>
                                            </div>
                                        ) : selectedMessage.syncStatus && selectedMessage.syncStatus !== 'sent' ? (
                                            <div className="rounded-md border border-amber-500/20 bg-amber-500/5 p-3">
                                                <p className="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    <AlertTriangle className="h-4 w-4" />
                                                    Sin respuesta enviada
                                                </p>
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    El webhook si guardo el mensaje, pero la automatizacion no logro completar el envio de la respuesta.
                                                </p>
                                            </div>
                                        ) : null}
                                    </>
                                ) : (
                                    <div className="rounded-md border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                        No hay mensaje seleccionado todavia.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-lg">Siguiente paso</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Desde aqui puedes conectar el router de agentes para que este inbox dispare el padre, el hijo de facturacion y el flujo de entrenamiento.
                                </p>
                                <div className="mt-4 rounded-md border bg-muted/20 p-3">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Entrenamiento pendiente
                                    </p>
                                    <p className="mt-2 text-2xl font-semibold">
                                        {trainingPendingCount}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Marca un mensaje con <code>#regla</code>, <code>#correccion</code> o <code>#ejemplo</code> para revisarlo aqui.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </>
    );
}

TelegramInbox.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? telegramEdit.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'Telegram inbox',
            href: props.currentTeam
                ? telegramInbox.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
