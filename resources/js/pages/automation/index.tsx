import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Bot, Cpu, Database, MessageCircle, Workflow, Zap } from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    edit as aiProviderEdit,
} from '@/actions/App/Http/Controllers/Automation/AiProviderController';
import {
    edit as dolibarrEdit,
} from '@/actions/App/Http/Controllers/Automation/DolibarrController';
import {
    edit as telegramEdit,
} from '@/actions/App/Http/Controllers/Automation/TelegramController';
import {
    index as agentsIndex,
} from '@/actions/App/Http/Controllers/Automation/AgentController';
import {
    dian as dianAutomation,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import AutomationFlowHeader from '@/components/automation-flow-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    AiProviderConfiguration,
    AutomationDolibarrSummary,
    AutomationSummary,
    TelegramConfiguration,
    Team,
} from '@/types';

type Props = {
    telegram: TelegramConfiguration;
    agents: AutomationSummary;
    dolibarr: AutomationDolibarrSummary;
    aiProvider: AiProviderConfiguration;
    currentTeam?: Team | null;
};

export default function AutomationIndex({
    telegram,
    agents,
    dolibarr,
    aiProvider,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug;

    return (
        <>
            <Head title="Automation" />

            <h1 className="sr-only">Automation</h1>

            <div className="relative space-y-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(0,0,0,0.08),transparent_45%),radial-gradient(circle_at_top_right,rgba(0,0,0,0.04),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.08),transparent_45%),radial-gradient(circle_at_top_right,rgba(255,255,255,0.04),transparent_35%)]" />
                <div className="absolute -right-24 top-24 -z-10 size-72 rounded-full bg-primary/10 blur-3xl" />
                <div className="absolute -left-16 top-56 -z-10 size-64 rounded-full bg-amber-500/10 blur-3xl" />

                <AutomationFlowHeader
                    badges={['Telegram', 'MCP tools', 'Agentes', 'Dolibarr']}
                    title="Automation control plane"
                    description="Prepara el flujo completo para arrancar pruebas: Telegram como entrada, MCP como capa de tools, agente como orquestador y Dolibarr como destino."
                    note="La idea es que el equipo llegue a esta pantalla y entienda de inmediato que ya esta listo, que falta y cual es el siguiente paso."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? telegramEdit.url(teamSlug) : '#'}>
                                    Configurar Telegram
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={teamSlug ? agentsIndex.url(teamSlug) : '#'}>
                                    Gestionar agentes
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? dianAutomation.url(teamSlug) : '#'}>
                                    Agente DIAN
                                </Link>
                            </Button>
                        </>
                    }
                    stages={[
                        {
                            label: 'Telegram',
                            description: telegram.isEnabled
                                ? 'Canal de entrada listo para recibir mensajes y validaciones.'
                                : 'Activa el canal para recibir tareas desde Telegram.',
                            active: telegram.isEnabled,
                        },
                        {
                            label: 'Proveedor IA',
                            description: aiProvider.isEnabled
                                ? `${aiProvider.providerLabel} y modelo ${aiProvider.model} listos para los agentes.`
                                : 'Configura GPT, Gemini u Ollama para que el agente pueda razonar.',
                            active: aiProvider.isEnabled,
                        },
                        {
                            label: 'MCP tools',
                            description: 'Las tools como get_customers, search_products y create_invoice viven aqui como la capa segura de integracion.',
                            active: dolibarr.hasApiLogin && dolibarr.hasApiPassword && dolibarr.hasApiUrl,
                        },
                        {
                            label: 'Agentes',
                            description: agents.enabled > 0
                                ? `${agents.enabled} de ${agents.total} agentes ya estan activos.`
                                : 'Crea el primer agente para empezar a probar el flujo end-to-end.',
                            active: agents.enabled > 0,
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-2xl border bg-background/80 p-4">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Telegram
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Estado actual
                                </p>
                                <p className="text-lg font-semibold">
                                    {telegram.isEnabled ? 'Activo' : 'Inactivo'}
                                </p>
                            </div>
                            <MessageCircle className="h-5 w-5 text-primary" />
                        </div>
                    </div>

                    <div className="rounded-2xl border bg-background/80 p-4">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Agentes
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    En funcionamiento
                                </p>
                                <p className="text-lg font-semibold">
                                    {agents.enabled}/{agents.total}
                                </p>
                            </div>
                            <Workflow className="h-5 w-5 text-primary" />
                        </div>
                    </div>

                    <div className="rounded-2xl border bg-background/80 p-4">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Objetivo
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Siguiente paso
                                </p>
                                <p className="text-lg font-semibold">
                                    Crear factura
                                </p>
                            </div>
                            <Zap className="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2 2xl:grid-cols-4">
                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-sky-500 via-cyan-400 to-emerald-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                                        <MessageCircle className="h-5 w-5" />
                                    </span>
                                    Telegram
                                </CardTitle>
                                <Badge
                                    variant={telegram.isEnabled ? 'default' : 'secondary'}
                                    className="rounded-full px-3 py-1"
                                >
                                    {telegram.isEnabled ? 'Activo' : 'Inactivo'}
                                </Badge>
                            </div>
                            <CardDescription className="max-w-md">
                                Define el canal de entrada, el chat por defecto y el secreto de webhook para recibir tareas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Token
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {telegram.hasToken ? 'Configurado' : 'Pendiente'}
                                    </p>
                                </div>
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Webhook
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {telegram.hasWebhookSecret ? 'Configurado' : 'Pendiente'}
                                    </p>
                                </div>
                            </div>

                            <Button className="w-full" asChild>
                                <Link href={teamSlug ? telegramEdit.url(teamSlug) : '#'}>
                                    Revisar Telegram
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Database className="h-5 w-5" />
                                    </span>
                                    Dolibarr
                                </CardTitle>
                                <Badge
                                    variant={dolibarr.hasApiLogin && dolibarr.hasApiPassword && dolibarr.hasApiUrl ? 'default' : 'secondary'}
                                    className="rounded-full px-3 py-1"
                                >
                                    {dolibarr.hasApiLogin && dolibarr.hasApiPassword && dolibarr.hasApiUrl ? 'Credenciales listas' : 'Pendiente'}
                                </Badge>
                            </div>
                            <CardDescription className="max-w-md">
                                Conecta el login, la contrasena y la URL de tu instancia de Dolibarr para que las tools y los agentes puedan operar.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Login
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {dolibarr.hasApiLogin ? 'Configurado' : 'Pendiente'}
                                    </p>
                                </div>
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Password
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {dolibarr.hasApiPassword ? 'Guardado' : 'Pendiente'}
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-2xl border bg-muted/20 p-4">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Detectadas
                                </p>
                                <p className="mt-2 text-base font-medium">
                                    {dolibarr.discoveredApiCount}
                                </p>
                                <p className="mt-2 text-sm font-medium text-muted-foreground">
                                    Importantes para facturas: {dolibarr.importantApiCount}
                                </p>
                            </div>

                            <Button className="w-full" asChild>
                                <Link href={teamSlug ? dolibarrEdit.url(teamSlug) : '#'}>
                                    Configurar Dolibarr
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-cyan-500 via-sky-400 to-indigo-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                                        <Cpu className="h-5 w-5" />
                                    </span>
                                    Proveedor IA
                                </CardTitle>
                                <Badge
                                    variant={aiProvider.isEnabled ? 'default' : 'secondary'}
                                    className="rounded-full px-3 py-1"
                                >
                                    {aiProvider.isEnabled ? 'Activo' : 'Inactivo'}
                                </Badge>
                            </div>
                            <CardDescription className="max-w-md">
                                Administra el motor de IA que usaran los agentes para consumir Dolibarr.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Proveedor
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {aiProvider.providerLabel}
                                    </p>
                                </div>
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Modelo
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {aiProvider.model}
                                    </p>
                                </div>
                            </div>

                            <Button className="w-full" asChild>
                                <Link href={teamSlug ? aiProviderEdit.url(teamSlug) : '#'}>
                                    Ver proveedor IA
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-amber-500 via-orange-400 to-rose-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                        <Bot className="h-5 w-5" />
                                    </span>
                                    Agentes
                                </CardTitle>
                                <Badge variant="secondary" className="rounded-full px-3 py-1">
                                    {agents.enabled}/{agents.total} activos
                                </Badge>
                            </div>
                            <CardDescription className="max-w-md">
                                Crea agentes simples y enfocados para que cada uno se encargue de una tarea concreta en Dolibarr.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Total
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {agents.total}
                                    </p>
                                </div>
                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Activos
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {agents.enabled}
                                    </p>
                                </div>
                            </div>

                            <Button className="w-full" asChild>
                                <Link href={teamSlug ? agentsIndex.url(teamSlug) : '#'}>
                                    Ver agentes
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AutomationIndex.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
