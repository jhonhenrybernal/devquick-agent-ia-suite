import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    edit as telegramEdit,
    inbox as telegramInbox,
    validateToken,
    detectChatId,
    syncWebhook,
    update as updateTelegram,
    testConnection,
} from '@/actions/App/Http/Controllers/Automation/TelegramController';
import AutomationFlowHeader from '@/components/automation-flow-header';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TelegramConfiguration, Team } from '@/types';

type Props = {
    telegramConfiguration: TelegramConfiguration;
    currentTeam?: Team | null;
};

export default function Telegram({
    telegramConfiguration,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug;

    return (
        <>
            <Head title="Telegram automation" />

            <h1 className="sr-only">Telegram automation</h1>

            <div className="relative space-y-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.14),transparent_42%),radial-gradient(circle_at_top_right,rgba(234,179,8,0.12),transparent_32%)]" />
                <div className="absolute -right-28 top-14 -z-10 size-72 rounded-full bg-sky-500/10 blur-3xl" />
                <div className="absolute -left-20 top-52 -z-10 size-64 rounded-full bg-amber-500/10 blur-3xl" />

                <AutomationFlowHeader
                    badges={['Canal de entrada', 'Validación segura', 'Telegram bot']}
                    title="Telegram bridge"
                    description="Configura el canal por donde entran las instrucciones para los agentes."
                    note="Este módulo no reemplaza Dolibarr. Solo recibe mensajes, los valida y prepara la interacción para que el agente haga su trabajo."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                    Volver al hub
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? telegramInbox.url(teamSlug) : '#'}>
                                    Ver inbox
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                    Ver estado
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </>
                    }
                    stages={[
                        {
                            label: 'Bot token',
                            description: telegramConfiguration.hasToken
                                ? 'Token guardado y listo para validar la comunicación.'
                                : 'Necesario para autenticar el bot con Telegram.',
                            active: telegramConfiguration.hasToken,
                        },
                        {
                            label: 'Detect chat ID',
                            description: telegramConfiguration.chatId
                                ? `Chat ID detectado: ${telegramConfiguration.chatId}.`
                                : 'Abre el bot y envía /start para detectar el chat ID.',
                            active: Boolean(telegramConfiguration.chatId),
                        },
                        {
                            label: 'Test message',
                            description: 'Envía un mensaje real para confirmar que Telegram responde.',
                            active: telegramConfiguration.isEnabled,
                        },
                        {
                            label: 'Webhook secret',
                            description: 'Se usa para validar que los webhooks entren desde el canal correcto.',
                            active: telegramConfiguration.isEnabled,
                        },
                    ]}
                />

                <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                    <div className="h-1 bg-gradient-to-r from-sky-500 via-cyan-400 to-emerald-400" />
                    <CardHeader className="space-y-2">
                        <div className="flex items-center justify-between gap-3">
                            <CardTitle className="text-xl">Bot settings</CardTitle>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant={
                                        telegramConfiguration.isEnabled
                                            ? 'default'
                                            : 'secondary'
                                    }
                                    className="rounded-full px-3 py-1"
                                >
                                    {telegramConfiguration.isEnabled
                                        ? 'Activo'
                                        : 'Inactivo'}
                                </Badge>
                                {telegramConfiguration.hasToken && (
                                    <Badge
                                        variant="secondary"
                                        className="rounded-full px-3 py-1"
                                    >
                                        Token guardado
                                    </Badge>
                                )}
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Define el token, el chat por defecto y el secreto del webhook.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...updateTelegram.form(teamSlug ?? '')}
                            options={{ preserveScroll: true }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="bot_token">
                                            Bot token
                                        </Label>
                                        <PasswordInput
                                            id="bot_token"
                                            name="bot_token"
                                            defaultValue={
                                                telegramConfiguration.botToken ??
                                                ''
                                            }
                                            autoComplete="off"
                                            placeholder={
                                                telegramConfiguration.hasToken
                                                    ? 'Leave blank to keep the current token'
                                                    : '123456:ABC-DEF...'
                                            }
                                        />
                                        <InputError
                                            message={errors.bot_token}
                                        />
                                    </div>

                                    <div className="grid gap-2 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="bot_username">
                                                Bot username
                                            </Label>
                                            <Input
                                                id="bot_username"
                                                name="bot_username"
                                                defaultValue={
                                                    telegramConfiguration.botUsername ??
                                                    ''
                                                }
                                                placeholder="my_bot"
                                            />
                                            <InputError
                                                message={errors.bot_username}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="chat_id">
                                                Default chat ID
                                            </Label>
                                            <Input
                                                id="chat_id"
                                                name="chat_id"
                                                defaultValue={
                                                    telegramConfiguration.chatId ??
                                                    ''
                                                }
                                                placeholder="123456789"
                                            />
                                            <InputError message={errors.chat_id} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="webhook_secret">
                                            Webhook secret
                                        </Label>
                                        <PasswordInput
                                            id="webhook_secret"
                                            name="webhook_secret"
                                            defaultValue={
                                                telegramConfiguration.webhookSecret ??
                                                ''
                                            }
                                            autoComplete="off"
                                            placeholder="Only letters, numbers, _ and -"
                                        />
                                        <InputError
                                            message={errors.webhook_secret}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Telegram only accepts A-Z, a-z, 0-9, underscore and dash here.
                                            {telegramConfiguration.hasWebhookSecret
                                                ? ' The stored value is preserved and can be revealed with the eye icon.'
                                                : ' A new safe secret will be generated if you leave it empty.'}
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="webhook_url">
                                            Webhook URL
                                        </Label>
                                        <div className="flex flex-col gap-2 sm:flex-row">
                                            <Input
                                                id="webhook_url"
                                                value={telegramConfiguration.webhookUrl ?? 'Se genera al guardar la configuracion'}
                                                readOnly
                                                className="flex-1"
                                            />
                                            {telegramConfiguration.webhookUrl ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a
                                                        href={telegramConfiguration.webhookUrl}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Abrir
                                                        <ExternalLink className="ml-2 h-4 w-4" />
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Telegram envia los mensajes a esta URL cuando el webhook queda registrado.
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border bg-background/80 p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    Webhook status
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Revisa si Telegram realmente esta apuntando a esta app.
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    telegramConfiguration.webhookStatusOk
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="rounded-full px-3 py-1"
                                            >
                                                {telegramConfiguration.webhookStatusOk
                                                    ? 'Conectado'
                                                    : 'Pendiente'}
                                            </Badge>
                                        </div>

                                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                                            <div className="rounded-xl border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    URL registrada
                                                </p>
                                                <p className="mt-1 break-all text-sm font-medium">
                                                    {telegramConfiguration.registeredWebhookUrl ??
                                                        'Telegram no ha registrado aun un webhook'}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    URL esperada
                                                </p>
                                                <p className="mt-1 break-all text-sm font-medium">
                                                    {telegramConfiguration.webhookUrl ??
                                                        'Se genera al guardar la configuracion'}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Coincide
                                                </p>
                                                <p className="mt-1 text-sm font-medium">
                                                    {telegramConfiguration.webhookMatchesExpectedUrl
                                                        ? 'Si'
                                                        : 'No'}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border bg-muted/20 p-3">
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Pendientes
                                                </p>
                                                <p className="mt-1 text-sm font-medium">
                                                    {telegramConfiguration.webhookPendingUpdateCount ??
                                                        'Sin dato'}
                                                </p>
                                            </div>
                                        </div>

                                        {telegramConfiguration.webhookLastErrorMessage ? (
                                            <div className="mt-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                                                <p className="font-medium">
                                                    Ultimo error de Telegram
                                                </p>
                                                <p className="mt-1">
                                                    {telegramConfiguration.webhookLastErrorMessage}
                                                </p>
                                                {telegramConfiguration.webhookLastErrorDate ? (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {telegramConfiguration.webhookLastErrorDate}
                                                    </p>
                                                ) : null}
                                            </div>
                                        ) : null}

                                        {telegramConfiguration.webhookStatusDescription ? (
                                            <p className="mt-3 text-xs text-muted-foreground">
                                                {telegramConfiguration.webhookStatusDescription}
                                            </p>
                                        ) : null}

                                        <div className="mt-4 flex flex-wrap gap-3">
                                            <Button variant="outline" asChild>
                                                <Link
                                                    href={telegramEdit.url(teamSlug ?? '')}
                                                >
                                                    Ver estado del webhook
                                                </Link>
                                            </Button>
                                            <Form
                                                {...syncWebhook.form(teamSlug ?? '')}
                                                options={{ preserveScroll: true }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={!telegramConfiguration.hasToken || processing}
                                                    >
                                                        Re-sincronizar webhook
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3 rounded-2xl border bg-muted/20 px-4 py-3">
                                        <Checkbox
                                            id="is_enabled"
                                            name="is_enabled"
                                            defaultChecked={
                                                telegramConfiguration.isEnabled
                                            }
                                            value="1"
                                        />
                                        <Label
                                            htmlFor="is_enabled"
                                            className="font-normal"
                                        >
                                            Activate integration
                                        </Label>
                                    </div>

                                    <p className="text-sm text-muted-foreground">
                                        If the token already exists, you can
                                        leave it blank to keep the current
                                        value.
                                    </p>

                                    <div className="flex flex-wrap gap-3">
                                        <Button disabled={processing}>
                                            Save Telegram
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                    <div className="h-1 bg-gradient-to-r from-amber-400 via-orange-400 to-rose-400" />
                    <CardHeader className="space-y-2">
                        <CardTitle className="text-xl">Connection checks</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Validate the token, detect a chat ID, or send a real Telegram message to confirm that everything is working.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Form
                                action={validateToken.url(teamSlug ?? '')}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <div className="flex h-full flex-col justify-between gap-3 rounded-2xl border bg-background/80 p-4">
                                        <div className="space-y-1">
                                            <p className="font-semibold">
                                                Validate token
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Checks that BotFather token is valid and the bot is reachable.
                                            </p>
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing || !telegramConfiguration.hasToken}
                                        >
                                            Validate token
                                        </Button>
                                    </div>
                                )}
                            </Form>

                            <Form
                                action={detectChatId.url(teamSlug ?? '')}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <div className="flex h-full flex-col justify-between gap-3 rounded-2xl border bg-background/80 p-4">
                                        <div className="space-y-1">
                                            <p className="font-semibold">
                                                Detect chat ID
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Reads Telegram updates and saves the last available chat ID.
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                First open the bot in Telegram and send /start so the bot can receive at least one update.
                                            </p>
                                            {telegramConfiguration.botUsername && (
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    className="h-auto px-0 text-sm font-medium"
                                                >
                                                    <a
                                                        href={`https://t.me/${telegramConfiguration.botUsername}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Open bot in Telegram
                                                        <ExternalLink className="ml-2 h-4 w-4" />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing || !telegramConfiguration.hasToken}
                                        >
                                            Detect chat ID
                                        </Button>
                                    </div>
                                )}
                            </Form>

                            <Form
                                action={testConnection.url(teamSlug ?? '')}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <div className="flex h-full flex-col justify-between gap-3 rounded-2xl border bg-background/80 p-4">
                                        <div className="space-y-1">
                                            <p className="font-semibold">
                                                Send test message
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Sends a real message to the configured chat ID.
                                            </p>
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={
                                                processing ||
                                                !telegramConfiguration.hasToken ||
                                                !telegramConfiguration.chatId
                                            }
                                        >
                                            Test Telegram connection
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Telegram.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'Telegram',
            href: props.currentTeam
                ? telegramEdit.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
