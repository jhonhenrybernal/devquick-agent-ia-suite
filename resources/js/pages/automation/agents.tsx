import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    destroy as destroyAgent,
    index as agentsIndex,
    show as showAgent,
    store as storeAgent,
} from '@/actions/App/Http/Controllers/Automation/AgentController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AutomationAgent, Team } from '@/types';

type Props = {
    agents: AutomationAgent[];
    currentTeam?: Team | null;
};

function isRootAgent(agent: AutomationAgent): boolean {
    return agent.parentAgentId === null || agent.parentAgentId === undefined;
}

function agentLabel(agent: AutomationAgent): string {
    if (isRootAgent(agent)) {
        return 'Padre';
    }

    if (agent.targetTool === 'create_invoice') {
        return 'Facturacion';
    }

    return 'Hijo';
}

function CreateAgentForm({
    agents,
    teamSlug,
}: {
    agents: AutomationAgent[];
    teamSlug: string;
}) {
    return (
        <section className="rounded-lg border bg-background">
            <div className="border-b px-4 py-3">
                <h2 className="text-lg font-semibold">Crear agente</h2>
                <p className="text-sm text-muted-foreground">
                    Deja el padre vacio para crear un nuevo orquestador o
                    elige uno existente para crear un hijo.
                </p>
            </div>

            <div className="p-4">
                <Form
                    {...storeAgent.form({
                        current_team: teamSlug,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                    resetOnSuccess
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="Agente de facturacion"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="target_tool">
                                        Tool objetivo
                                    </Label>
                                    <Input
                                        id="target_tool"
                                        name="target_tool"
                                        defaultValue="create_invoice"
                                        placeholder="create_invoice"
                                    />
                                    <InputError message={errors.target_tool} />
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_agent_id">
                                        Padre
                                    </Label>
                                    <select
                                        id="parent_agent_id"
                                        name="parent_agent_id"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                        defaultValue=""
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
                                    <Label htmlFor="trigger_keyword">
                                        Trigger keyword
                                    </Label>
                                    <Input
                                        id="trigger_keyword"
                                        name="trigger_keyword"
                                        placeholder="monthly invoice"
                                    />
                                    <InputError message={errors.trigger_keyword} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripcion</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    placeholder="Que hace este agente"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="instructions">Instrucciones</Label>
                                <Textarea
                                    id="instructions"
                                    name="instructions"
                                    className="min-h-32"
                                    placeholder="Instrucciones del agente"
                                />
                                <InputError message={errors.instructions} />
                            </div>

                            <div className="flex items-center gap-3 rounded-md border px-4 py-3">
                                <Checkbox
                                    id="is_enabled"
                                    name="is_enabled"
                                    value="1"
                                    defaultChecked
                                />
                                <Label htmlFor="is_enabled" className="font-normal">
                                    Activo
                                </Label>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button disabled={processing}>
                                    Guardar agente
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

export default function Agents({ agents, currentTeam }: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const orderedAgents = [...agents].sort((left, right) => {
        if (isRootAgent(left) && !isRootAgent(right)) {
            return -1;
        }

        if (!isRootAgent(left) && isRootAgent(right)) {
            return 1;
        }

        return left.name.localeCompare(right.name);
    });

    return (
        <>
            <Head title="Agentes" />

            <h1 className="sr-only">Agentes</h1>

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">
                            Automation
                        </p>
                        <h2 className="text-2xl font-semibold">Agentes listos</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Aqui ves el padre que no se edita y el hijo de facturacion que si se usa para crear invoices.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                Volver al hub
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="#new-agent">
                                Nuevo agente
                                <Plus className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="rounded-lg border bg-background">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-lg font-semibold">Lista de agentes</h2>
                        <p className="text-sm text-muted-foreground">
                            Usa el ojo para ver y el lapiz para editar.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-left text-sm">
                            <thead className="bg-muted/30">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nombre</th>
                                    <th className="px-4 py-3 font-medium">Tipo</th>
                                    <th className="px-4 py-3 font-medium">Tool</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                    <th className="px-4 py-3 font-medium">Padre</th>
                                    <th className="px-4 py-3 font-medium text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orderedAgents.length > 0 ? (
                                    orderedAgents.map((agent) => {
                                        const locked = isRootAgent(agent);

                                        return (
                                            <tr key={agent.id} className="align-top">
                                                <td className="px-4 py-4">
                                                    <div className="font-medium">
                                                        {agent.name}
                                                    </div>
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {agent.description || 'Sin descripcion'}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge
                                                        variant={locked ? 'secondary' : 'outline'}
                                                        className="rounded-full"
                                                    >
                                                        {agentLabel(agent)}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4 font-mono text-xs">
                                                    {agent.targetTool}
                                                </td>
                                                <td className="px-4 py-4">
                                                    <Badge
                                                        variant={agent.isEnabled ? 'default' : 'secondary'}
                                                        className="rounded-full"
                                                    >
                                                        {agent.isEnabled ? 'Activo' : 'Inactivo'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-4 text-sm text-muted-foreground">
                                                    {agent.parentAgentName ?? 'Sin padre'}
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
                                                            <Link
                                                                href={showAgent.url({
                                                                    current_team: teamSlug,
                                                                    automation_agent: agent.id,
                                                                })}
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                                <span className="sr-only">Ver</span>
                                                            </Link>
                                                        </Button>

                                                        {!locked ? (
                                                            <>
                                                                <Button
                                                                    asChild
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-8"
                                                                    title="Editar"
                                                                >
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
                                                                        <Pencil className="h-4 w-4" />
                                                                        <span className="sr-only">Editar</span>
                                                                    </Link>
                                                                </Button>

                                                                <Form
                                                                    {...destroyAgent.form({
                                                                        current_team: teamSlug,
                                                                        automation_agent: agent.id,
                                                                    })}
                                                                    options={{ preserveScroll: true }}
                                                                >
                                                                    {({ processing }) => (
                                                                        <Button
                                                                            type="submit"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-8 text-destructive hover:text-destructive"
                                                                            title="Eliminar"
                                                                            disabled={processing}
                                                                        >
                                                                            <Trash2 className="h-4 w-4" />
                                                                            <span className="sr-only">
                                                                                Eliminar
                                                                            </span>
                                                                        </Button>
                                                                    )}
                                                                </Form>
                                                            </>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Bloqueado
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                                        >
                                            No hay agentes creados aun.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div id="new-agent">
                    <CreateAgentForm agents={agents} teamSlug={teamSlug} />
                </div>
            </div>
        </>
    );
}

Agents.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'Agentes',
            href: props.currentTeam
                ? agentsIndex.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
