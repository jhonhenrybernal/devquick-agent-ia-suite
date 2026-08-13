import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Save } from 'lucide-react';
import {
    index as agentsIndex,
    show as showAgent,
    update as updateAgent,
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
    agent: AutomationAgent;
    mode?: string;
    isLocked: boolean;
    currentTeam?: Team | null;
};

export default function AgentPage({
    agent,
    mode = 'view',
    isLocked,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug ?? '';
    const isEditing = mode === 'edit' && !isLocked;

    return (
        <>
            <Head title={agent.name} />

            <h1 className="sr-only">{agent.name}</h1>

            <div className="space-y-6">
                <div className="flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">
                            Automation
                        </p>
                        <h2 className="text-2xl font-semibold">{agent.name}</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isLocked
                                ? 'Este agente es el padre y solo se puede ver.'
                                : 'Puedes ver el agente o entrar en modo edicion.'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <Link href={teamSlug ? agentsIndex.url(teamSlug) : '#'}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Volver a la lista
                            </Link>
                        </Button>

                        {!isLocked ? (
                            isEditing ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={showAgent.url({
                                            current_team: teamSlug,
                                            automation_agent: agent.id,
                                        })}
                                    >
                                        Ver
                                    </Link>
                                </Button>
                            ) : (
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
                            )
                        ) : null}
                    </div>
                </div>

                <section className="rounded-lg border bg-background">
                    <div className="border-b px-4 py-3">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">Ficha del agente</h2>
                            <div className="flex flex-wrap gap-2">
                                <Badge
                                    variant={agent.isEnabled ? 'default' : 'secondary'}
                                    className="rounded-full"
                                >
                                    {agent.isEnabled ? 'Activo' : 'Inactivo'}
                                </Badge>
                                <Badge
                                    variant={isLocked ? 'secondary' : 'outline'}
                                    className="rounded-full"
                                >
                                    {isLocked ? 'Bloqueado' : 'Editable'}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-0 md:grid-cols-2">
                        <dl className="divide-y divide-border md:col-span-1">
                            <div className="grid gap-1 px-4 py-3">
                                <dt className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Nombre
                                </dt>
                                <dd className="text-sm font-medium">{agent.name}</dd>
                            </div>
                            <div className="grid gap-1 px-4 py-3">
                                <dt className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Tipo
                                </dt>
                                <dd className="text-sm font-medium">
                                    {isLocked ? 'Padre orquestador' : 'Hijo operativo'}
                                </dd>
                            </div>
                            <div className="grid gap-1 px-4 py-3">
                                <dt className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Padre
                                </dt>
                                <dd className="text-sm font-medium">
                                    {agent.parentAgentName ?? 'Sin padre'}
                                </dd>
                            </div>
                            <div className="grid gap-1 px-4 py-3">
                                <dt className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Tool
                                </dt>
                                <dd className="text-sm font-medium font-mono">
                                    {agent.targetTool}
                                </dd>
                            </div>
                            <div className="grid gap-1 px-4 py-3">
                                <dt className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Trigger
                                </dt>
                                <dd className="text-sm font-medium">
                                    {agent.triggerKeyword ?? 'Sin trigger'}
                                </dd>
                            </div>
                        </dl>

                        <div className="border-t md:border-l md:border-t-0">
                            <div className="grid gap-1 px-4 py-3">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Descripcion
                                </p>
                                <p className="text-sm">
                                    {agent.description ?? 'Sin descripcion'}
                                </p>
                            </div>
                            <div className="border-t px-4 py-3">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Instrucciones
                                </p>
                                <pre className="mt-2 overflow-auto rounded-md border bg-muted/20 p-3 text-sm whitespace-pre-wrap">
                                    {agent.instructions}
                                </pre>
                            </div>
                            <div className="border-t px-4 py-3">
                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                    Hijos
                                </p>
                                <p className="text-sm font-medium">
                                    {agent.childAgentsCount ?? 0}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {isLocked ? (
                    <section className="rounded-lg border border-dashed bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                        Este agente padre no se puede editar. Solo sirve para
                        enrutar tareas hacia los hijos.
                    </section>
                ) : isEditing ? (
                    <section className="rounded-lg border bg-background">
                        <div className="border-b px-4 py-3">
                            <h2 className="text-lg font-semibold">Editar agente</h2>
                            <p className="text-sm text-muted-foreground">
                                Ajusta los campos operativos y guarda los cambios.
                            </p>
                        </div>

                        <div className="p-4">
                            <Form
                                {...updateAgent.form({
                                    current_team: teamSlug,
                                    automation_agent: agent.id,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="parent_agent_id"
                                            value={agent.parentAgentId ?? ''}
                                        />

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nombre
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    defaultValue={agent.name}
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
                                                    defaultValue={agent.targetTool}
                                                />
                                                <InputError message={errors.target_tool} />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="trigger_keyword">
                                                Trigger keyword
                                            </Label>
                                            <Input
                                                id="trigger_keyword"
                                                name="trigger_keyword"
                                                defaultValue={agent.triggerKeyword ?? ''}
                                            />
                                            <InputError message={errors.trigger_keyword} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="description">
                                                Descripcion
                                            </Label>
                                            <Input
                                                id="description"
                                                name="description"
                                                defaultValue={agent.description ?? ''}
                                            />
                                            <InputError message={errors.description} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="instructions">
                                                Instrucciones
                                            </Label>
                                            <Textarea
                                                id="instructions"
                                                name="instructions"
                                                defaultValue={agent.instructions}
                                                className="min-h-40"
                                            />
                                            <InputError message={errors.instructions} />
                                        </div>

                                        <div className="flex items-center gap-3 rounded-md border px-4 py-3">
                                            <Checkbox
                                                id="is_enabled"
                                                name="is_enabled"
                                                value="1"
                                                defaultChecked={agent.isEnabled}
                                            />
                                            <Label htmlFor="is_enabled" className="font-normal">
                                                Activo
                                            </Label>
                                        </div>

                                        <div className="flex flex-wrap gap-3">
                                            <Button disabled={processing}>
                                                Guardar cambios
                                                <Save className="ml-2 h-4 w-4" />
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    </section>
                ) : (
                    <section className="rounded-lg border bg-background px-4 py-4 text-sm text-muted-foreground">
                        Esta vista es solo lectura. Pulsa Editar para cambiar el
                        agente.
                    </section>
                )}
            </div>
        </>
    );
}

AgentPage.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? agentsIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'Agente',
            href: props.currentTeam
                ? agentsIndex.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
