import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Bot, ExternalLink, MessageCircle, Pencil, Workflow } from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    index as agentsIndex,
    show as showAgent,
} from '@/actions/App/Http/Controllers/Automation/AgentController';
import {
    inbox as telegramInbox,
} from '@/actions/App/Http/Controllers/Automation/TelegramController';
import AutomationFlowHeader from '@/components/automation-flow-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { AutomationAgent, Team } from '@/types';

type Props = {
    parentAgent?: AutomationAgent | null;
    operationalAgent?: AutomationAgent | null;
    trainingAgent?: AutomationAgent | null;
    agentCount: number;
    readyCount: number;
    checklist: string[];
    currentTeam?: Team | null;
};

function agentStatusLabel(agent?: AutomationAgent | null): string {
    if (!agent) {
        return 'No creado';
    }

    return agent.isEnabled ? 'Activo' : 'Inactivo';
}

function agentStatusVariant(agent?: AutomationAgent | null): 'default' | 'secondary' {
    if (!agent) {
        return 'secondary';
    }

    return agent.isEnabled ? 'default' : 'secondary';
}

function AgentCard({
    title,
    subtitle,
    agent,
    teamSlug,
}: {
    title: string;
    subtitle: string;
    agent?: AutomationAgent | null;
    teamSlug: string;
}) {
    const canEdit = Boolean(agent && agent.parentAgentId !== null && agent.parentAgentId !== undefined);

    return (
        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
            <div className="h-1 bg-gradient-to-r from-emerald-500 via-cyan-400 to-sky-400" />
            <CardHeader className="space-y-3">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-xl">
                            <span className="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Bot className="h-5 w-5" />
                            </span>
                            {title}
                        </CardTitle>
                        <CardDescription className="mt-2 max-w-md">
                            {subtitle}
                        </CardDescription>
                    </div>

                    <Badge
                        variant={agentStatusVariant(agent)}
                        className="rounded-full px-3 py-1"
                    >
                        {agentStatusLabel(agent)}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {agent ? (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-2xl border bg-muted/20 p-4">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Tool
                                </p>
                                <p className="mt-2 font-mono text-sm font-medium">
                                    {agent.targetTool}
                                </p>
                            </div>
                            <div className="rounded-2xl border bg-muted/20 p-4">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Trigger
                                </p>
                                <p className="mt-2 text-sm font-medium">
                                    {agent.triggerKeyword ?? 'Sin trigger'}
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl border bg-muted/20 p-4">
                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                Descripcion
                            </p>
                            <p className="mt-2 text-sm">
                                {agent.description ?? 'Sin descripcion'}
                            </p>
                        </div>

                        <div className="rounded-2xl border bg-muted/20 p-4">
                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                Instrucciones
                            </p>
                            <p className="mt-2 line-clamp-5 text-sm leading-6 text-muted-foreground">
                                {agent.instructions}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Button variant="outline" asChild>
                                <Link
                                    href={showAgent.url(
                                        {
                                            current_team: teamSlug,
                                            automation_agent: agent.id,
                                        },
                                        {
                                            query: {
                                                mode: 'view',
                                            },
                                        },
                                    )}
                                >
                                    Ver agente
                                    <ExternalLink className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>

                            {canEdit ? (
                                <Button asChild>
                                    <Link
                                        href={showAgent.url(
                                            {
                                                current_team: teamSlug,
                                                automation_agent: agent.id,
                                            },
                                            {
                                                query: {
                                                    mode: 'edit',
                                                },
                                            },
                                        )}
                                    >
                                        <Pencil className="mr-2 h-4 w-4" />
                                        Editar
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </>
                ) : (
                    <div className="rounded-2xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                        Este agente todavia no existe en la configuracion del equipo.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function DianAutomation({
    parentAgent,
    operationalAgent,
    trainingAgent,
    agentCount,
    readyCount,
    checklist,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Agente DIAN" />

            <h1 className="sr-only">Agente DIAN</h1>

            <div className="relative space-y-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.12),transparent_45%),radial-gradient(circle_at_top_right,rgba(14,165,233,0.08),transparent_35%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.12),transparent_45%),radial-gradient(circle_at_top_right,rgba(14,165,233,0.08),transparent_35%)]" />
                <div className="absolute -right-24 top-24 -z-10 size-72 rounded-full bg-emerald-500/10 blur-3xl" />
                <div className="absolute -left-16 top-56 -z-10 size-64 rounded-full bg-sky-500/10 blur-3xl" />

                <AutomationFlowHeader
                    badges={['DIAN', 'Contabilidad', 'Entrenamiento', 'Telegram']}
                    title="Agente DIAN"
                    description="Un espacio dedicado para revisar el agente contable que ayuda con impuestos, exogena y declaraciones, junto con su flujo de entrenamiento."
                    note="Aqui el contador puede ver el agente operativo, revisar el curador de entrenamiento y entrar al inbox de Telegram para validar correcciones."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                    Volver al hub
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? agentsIndex.url(teamSlug) : '#'}>
                                    Ver agentes
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={teamSlug ? telegramInbox.url(teamSlug) : '#'}>
                                    Abrir Telegram
                                    <MessageCircle className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </>
                    }
                    stages={[
                        {
                            label: 'Orquestador',
                            description: parentAgent
                                ? `${parentAgent.name} decide si la tarea va a contabilidad o a otro flujo.`
                                : 'Falta el agente padre orquestador.',
                            active: Boolean(parentAgent?.isEnabled),
                        },
                        {
                            label: 'Agente DIAN',
                            description: operationalAgent
                                ? 'Revisa impuestos, exogena, vencimientos y listas de chequeo.'
                                : 'Falta el agente operativo DIAN.',
                            active: Boolean(operationalAgent?.isEnabled),
                        },
                        {
                            label: 'Entrenamiento',
                            description: trainingAgent
                                ? 'Captura correcciones y convierte aprendizaje en reglas aprobadas.'
                                : 'Falta el curador de entrenamiento DIAN.',
                            active: Boolean(trainingAgent?.isEnabled),
                        },
                    ]}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-2xl border bg-background/80 p-4">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Agentes
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Configurados
                                </p>
                                <p className="text-lg font-semibold">
                                    {readyCount}/{agentCount}
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
                                    Flujo principal
                                </p>
                                <p className="text-lg font-semibold">
                                    Declaraciones y exogena
                                </p>
                            </div>
                            <BookOpen className="h-5 w-5 text-primary" />
                        </div>
                    </div>

                    <div className="rounded-2xl border bg-background/80 p-4">
                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                            Estado
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Entrenamiento guiado
                                </p>
                                <p className="text-lg font-semibold">
                                    Listo para iterar
                                </p>
                            </div>
                            <ArrowRight className="h-5 w-5 text-primary" />
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <AgentCard
                        title="Agente operativo DIAN"
                        subtitle="Este es el agente que atiende el trabajo contable real: impuestos, exogena, vencimientos y listas de revision."
                        agent={operationalAgent}
                        teamSlug={teamSlug}
                    />

                    <AgentCard
                        title="Curador de entrenamiento"
                        subtitle="Este agente no responde al cliente final. Sirve para capturar correcciones, ejemplos y reglas que luego pasan al operativo."
                        agent={trainingAgent}
                        teamSlug={teamSlug}
                    />
                </div>

                <section className="rounded-2xl border bg-background/90 shadow-sm">
                    <div className="border-b px-6 py-4">
                        <h2 className="text-lg font-semibold">Flujo de entrenamiento</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            La idea es que el contador pueda corregir sin romper la operacion diaria.
                        </p>
                    </div>

                    <div className="grid gap-4 p-6 lg:grid-cols-[1.3fr_0.7fr]">
                        <div className="space-y-4">
                            {checklist.map((item, index) => (
                                <div
                                    key={item}
                                    className="flex items-start gap-3 rounded-2xl border bg-muted/20 px-4 py-3"
                                >
                                    <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                        {index + 1}
                                    </div>
                                    <p className="text-sm leading-6">{item}</p>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-4 rounded-2xl border bg-muted/20 p-4">
                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                Acciones rapidas
                            </p>

                            <div className="space-y-3">
                                <Button className="w-full" asChild>
                                    <Link href={teamSlug ? telegramInbox.url(teamSlug) : '#'}>
                                        Revisar mensajes de Telegram
                                        <MessageCircle className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="outline" className="w-full" asChild>
                                    <Link href={teamSlug ? agentsIndex.url(teamSlug) : '#'}>
                                        Volver a la tabla de agentes
                                    </Link>
                                </Button>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Cuando cambie una regla tributaria, actualiza primero este flujo y luego el agente operativo.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

DianAutomation.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'DIAN',
            href: props.currentTeam
                ? '#'
                : '/',
        },
    ],
});
