import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    AutomationAgent,
    ScheduledAutomation,
    Team,
} from '@/types';

type Props = {
    scheduledAutomations: ScheduledAutomation[];
    selectedScheduledAutomation?: ScheduledAutomation | null;
    mode?: string;
    agents: Pick<AutomationAgent, 'id' | 'name' | 'parentAgentId' | 'targetTool'>[];
    currentTeam?: Team | null;
};

function statusLabel(status: ScheduledAutomation['status']): string {
    return {
        draft: 'Borrador',
        active: 'Activo',
        paused: 'Pausado',
        completed: 'Completado',
        failed: 'Fallido',
    }[status];
}

function triggerLabel(triggerType: ScheduledAutomation['triggerType']): string {
    return {
        manual: 'Manual',
        interval: 'Intervalo',
        cron: 'Cron',
    }[triggerType];
}

function ScheduledAutomationForm({
    teamSlug,
    selectedScheduledAutomation,
    agents,
}: {
    teamSlug: string;
    selectedScheduledAutomation?: ScheduledAutomation | null;
    agents: Props['agents'];
}) {
    const isEditing = selectedScheduledAutomation !== null && selectedScheduledAutomation !== undefined;
    const action = isEditing
        ? `/${teamSlug}/automation/scheduled-automations/${selectedScheduledAutomation.id}`
        : `/${teamSlug}/automation/scheduled-automations`;
    const method = isEditing ? 'patch' : 'post';

    return (
        <section className="rounded-lg border bg-background">
            <div className="border-b px-4 py-3">
                <h2 className="text-lg font-semibold">
                    {isEditing ? 'Editar tarea programada' : 'Crear tarea programada'}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Define si se dispara por intervalo o por cron y a qué agente debe delegarse.
                </p>
            </div>

            <div className="p-4">
                <Form action={action} method={method} className="space-y-4" resetOnSuccess>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={selectedScheduledAutomation?.name ?? ''}
                                        placeholder="Cierre mensual de facturación"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Estado</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue={selectedScheduledAutomation?.status ?? 'draft'}
                                    >
                                        <option value="draft">Borrador</option>
                                        <option value="active">Activo</option>
                                        <option value="paused">Pausado</option>
                                        <option value="completed">Completado</option>
                                        <option value="failed">Fallido</option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="trigger_type">Disparo</Label>
                                    <select
                                        id="trigger_type"
                                        name="trigger_type"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue={selectedScheduledAutomation?.triggerType ?? 'interval'}
                                    >
                                        <option value="manual">Manual</option>
                                        <option value="interval">Intervalo</option>
                                        <option value="cron">Cron</option>
                                    </select>
                                    <InputError message={errors.trigger_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timezone">Zona horaria</Label>
                                    <Input
                                        id="timezone"
                                        name="timezone"
                                        defaultValue={selectedScheduledAutomation?.timezone ?? 'America/Bogota'}
                                    />
                                    <InputError message={errors.timezone} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="interval_value">Valor intervalo</Label>
                                    <Input
                                        id="interval_value"
                                        name="interval_value"
                                        type="number"
                                        min="1"
                                        defaultValue={selectedScheduledAutomation?.intervalValue ?? 1}
                                    />
                                    <InputError message={errors.interval_value} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="interval_unit">Unidad</Label>
                                    <select
                                        id="interval_unit"
                                        name="interval_unit"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue={selectedScheduledAutomation?.intervalUnit ?? 'months'}
                                    >
                                        <option value="minutes">Minutos</option>
                                        <option value="hours">Horas</option>
                                        <option value="days">Dias</option>
                                        <option value="weeks">Semanas</option>
                                        <option value="months">Meses</option>
                                    </select>
                                    <InputError message={errors.interval_unit} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="cron_expression">Cron</Label>
                                    <Input
                                        id="cron_expression"
                                        name="cron_expression"
                                        defaultValue={selectedScheduledAutomation?.cronExpression ?? ''}
                                        placeholder="0 8 * * 1"
                                    />
                                    <InputError message={errors.cron_expression} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_agent_id">Agente padre</Label>
                                    <select
                                        id="parent_agent_id"
                                        name="parent_agent_id"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue={selectedScheduledAutomation?.parentAgentId ?? ''}
                                    >
                                        <option value="">Sin padre</option>
                                        {agents.map((agent) => (
                                            <option key={agent.id} value={agent.id}>
                                                {agent.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.parent_agent_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="child_agent_id">Agente hijo</Label>
                                    <select
                                        id="child_agent_id"
                                        name="child_agent_id"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue={selectedScheduledAutomation?.childAgentId ?? ''}
                                    >
                                        <option value="">Sin hijo</option>
                                        {agents.map((agent) => (
                                            <option key={agent.id} value={agent.id}>
                                                {agent.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.child_agent_id} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripcion</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    defaultValue={selectedScheduledAutomation?.description ?? ''}
                                    placeholder="Resume por qué existe esta tarea"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="context">Contexto</Label>
                                <Textarea
                                    id="context"
                                    name="context"
                                    defaultValue={(selectedScheduledAutomation?.payload?.context as string | undefined) ?? ''}
                                    className="min-h-28"
                                    placeholder="Contexto inicial para el agente o tarea"
                                />
                                <InputError message={errors.context} />
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button disabled={processing}>
                                    {isEditing ? 'Guardar cambios' : 'Guardar tarea'}
                                    <Save className="ml-2 h-4 w-4" />
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}

export default function ScheduledAutomations({
    scheduledAutomations,
    selectedScheduledAutomation,
    mode = 'list',
    agents,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const isEditing = mode === 'edit' && selectedScheduledAutomation !== null && selectedScheduledAutomation !== undefined;

    return (
        <>
            <Head title="Tareas programadas" />

            <h1 className="sr-only">Tareas programadas</h1>

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">
                            Automation
                        </p>
                        <h2 className="text-2xl font-semibold">Tareas programadas</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Convierte frases recurrentes del chat en tareas que el scheduler ejecuta por ti.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <Link href={teamSlug ? `/${teamSlug}/automation` : '#'}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver al hub
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="#new-scheduled-automation">
                                Nueva tarea
                                <Plus className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="rounded-lg border bg-background">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-lg font-semibold">Lista de tareas</h2>
                        <p className="text-sm text-muted-foreground">
                            Usa el ojo para ver y el lapiz para editar.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-left text-sm">
                            <thead className="bg-muted/30">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium">Disparo</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                    <th className="px-4 py-3 font-medium">Próxima ejecución</th>
                                    <th className="px-4 py-3 font-medium">Agentes</th>
                                    <th className="px-4 py-3 font-medium text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {scheduledAutomations.length > 0 ? (
                                    scheduledAutomations.map((scheduledAutomation) => (
                                        <tr key={scheduledAutomation.id} className="align-top">
                                            <td className="px-4 py-4">
                                                <div className="font-medium">{scheduledAutomation.name}</div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {scheduledAutomation.description || 'Sin descripcion'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <Badge variant="outline" className="rounded-full">
                                                    {triggerLabel(scheduledAutomation.triggerType)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4">
                                                <Badge
                                                    variant={scheduledAutomation.status === 'active' ? 'default' : 'secondary'}
                                                    className="rounded-full"
                                                >
                                                    {statusLabel(scheduledAutomation.status)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-muted-foreground">
                                                {scheduledAutomation.nextRunAt ?? 'Sin fecha'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-muted-foreground">
                                                {scheduledAutomation.parentAgentName ?? 'Sin padre'}
                                                {' / '}
                                                {scheduledAutomation.childAgentName ?? 'Sin hijo'}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Ver"
                                                    >
                                                        <Link href={`/${teamSlug}/automation/scheduled-automations/${scheduledAutomation.id}`}>
                                                            <Eye className="h-4 w-4" />
                                                            <span className="sr-only">Ver</span>
                                                        </Link>
                                                    </Button>

                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Editar"
                                                    >
                                                        <Link href={`/${teamSlug}/automation/scheduled-automations/${scheduledAutomation.id}?mode=edit`}>
                                                            <Pencil className="h-4 w-4" />
                                                            <span className="sr-only">Editar</span>
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                                        >
                                            No hay tareas programadas aun.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                    <div id="new-scheduled-automation">
                        <ScheduledAutomationForm
                            teamSlug={teamSlug}
                            selectedScheduledAutomation={isEditing ? selectedScheduledAutomation : null}
                            agents={agents}
                        />
                    </div>

                    <section className="rounded-lg border bg-background">
                        <div className="border-b px-4 py-3">
                            <h2 className="text-lg font-semibold">Detalle</h2>
                            <p className="text-sm text-muted-foreground">
                                Aqui ves la tarea seleccionada y sus ultimas ejecuciones.
                            </p>
                        </div>

                        <div className="space-y-4 p-4">
                            {selectedScheduledAutomation ? (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-2xl border bg-muted/20 p-4">
                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">Estado</p>
                                            <p className="mt-2 text-base font-medium">{statusLabel(selectedScheduledAutomation.status)}</p>
                                        </div>
                                        <div className="rounded-2xl border bg-muted/20 p-4">
                                            <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">Disparo</p>
                                            <p className="mt-2 text-base font-medium">{triggerLabel(selectedScheduledAutomation.triggerType)}</p>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">Ultimo resultado</p>
                                        <p className="mt-2 text-sm">
                                            {selectedScheduledAutomation.lastResult ?? 'Aun no hay ejecuciones registradas.'}
                                        </p>
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Selecciona una tarea en la tabla para ver su detalle.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

ScheduledAutomations.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam ? `/${props.currentTeam.slug}/automation` : '/',
        },
        {
            title: 'Tareas programadas',
            href: props.currentTeam ? `/${props.currentTeam.slug}/automation/scheduled-automations` : '/',
        },
    ],
});
