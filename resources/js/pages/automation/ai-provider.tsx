import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ExternalLink,
    Cpu,
    Sparkles,
    Server,
    ShieldAlert,
} from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    edit as aiProviderEdit,
    testConnection,
    update as updateAiProvider,
} from '@/actions/App/Http/Controllers/Automation/AiProviderController';
import AutomationFlowHeader from '@/components/automation-flow-header';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    AiProviderConfiguration,
    AiProviderOption,
    Team,
} from '@/types';

type Props = {
    aiProviderConfiguration: AiProviderConfiguration;
    providerOptions: AiProviderOption[];
    currentTeam?: Team | null;
};

export default function AiProvider({
    aiProviderConfiguration,
    providerOptions,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug;

    return (
        <>
            <Head title="AI provider automation" />

            <h1 className="sr-only">AI provider automation</h1>

            <div className="relative space-y-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.14),transparent_42%),radial-gradient(circle_at_top_right,rgba(249,115,22,0.12),transparent_32%)]" />
                <div className="absolute -right-28 top-16 -z-10 size-72 rounded-full bg-cyan-500/10 blur-3xl" />
                <div className="absolute -left-20 top-56 -z-10 size-64 rounded-full bg-amber-500/10 blur-3xl" />

                <AutomationFlowHeader
                    badges={['GPT', 'Gemini', 'Ollama local', 'AI provider']}
                    title="AI provider"
                    description="Configura el proveedor de inteligencia artificial que usara el sistema para los agentes."
                    note="Aqui puedes cambiar entre OpenAI, Gemini u Ollama local, validar la comunicacion y abrir la guia oficial de instalacion cuando haga falta."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                    Volver al hub
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={teamSlug ? aiProviderEdit.url(teamSlug) : '#'}>
                                    Ver configuracion
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </>
                    }
                    stages={[
                        {
                            label: 'Provider',
                            description: `Proveedor actual: ${aiProviderConfiguration.providerLabel}.`,
                            active: aiProviderConfiguration.isEnabled,
                        },
                        {
                            label: 'Model',
                            description: aiProviderConfiguration.model
                                ? `Modelo activo: ${aiProviderConfiguration.model}.`
                                : 'Define el modelo que usaran los agentes.',
                            active: Boolean(aiProviderConfiguration.model),
                        },
                        {
                            label: 'API key',
                            description: aiProviderConfiguration.hasApiKey
                                ? 'La clave ya esta guardada.'
                                : 'Necesaria para autenticar el proveedor.',
                            active: aiProviderConfiguration.hasApiKey,
                        },
                        {
                            label: 'Connection test',
                            description: 'Valida que el proveedor pueda responder antes de usarlo en agentes.',
                            active: aiProviderConfiguration.isEnabled,
                        },
                    ]}
                />

                <div className="grid gap-4 md:grid-cols-3">
                    {providerOptions.map((option) => {
                        const isSelected =
                            option.value === aiProviderConfiguration.provider;

                        return (
                            <div
                                key={option.value}
                                className="rounded-2xl border bg-background/80 p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                            {option.label}
                                        </p>
                                        <p className="mt-2 text-base font-semibold">
                                            {option.description}
                                        </p>
                                    </div>
                                    <Sparkles className="h-5 w-5 text-primary" />
                                </div>

                                <div className="mt-4 flex items-center justify-between gap-3">
                                    <Badge
                                        variant={isSelected ? 'default' : 'secondary'}
                                        className="rounded-full px-3 py-1"
                                    >
                                        {isSelected ? 'Seleccionado' : 'Disponible'}
                                    </Badge>

                                    <Button asChild variant="ghost" className="h-auto px-0">
                                        <a
                                            href={
                                                option.value === 'openai'
                                                    ? 'https://platform.openai.com/docs/quickstart/make-your-first-api-request'
                                                    : option.value === 'gemini'
                                                        ? 'https://ai.google.dev/gemini-api/docs/get-started'
                                                        : 'https://www.ollama.com/download'
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Abrir guia
                                            <ExternalLink className="ml-2 h-4 w-4" />
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-cyan-500 via-sky-400 to-emerald-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                                        <Cpu className="h-5 w-5" />
                                    </span>
                                    Provider setup
                                </CardTitle>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant={
                                            aiProviderConfiguration.isEnabled
                                                ? 'default'
                                                : 'secondary'
                                        }
                                        className="rounded-full px-3 py-1"
                                    >
                                        {aiProviderConfiguration.isEnabled
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </Badge>
                                    <Badge variant="secondary" className="rounded-full px-3 py-1">
                                        {aiProviderConfiguration.providerLabel}
                                    </Badge>
                                </div>
                            </div>
                            <CardDescription className="max-w-md">
                                Define el proveedor, el modelo y las credenciales que usara la automatizacion.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...updateAiProvider.form(teamSlug ?? '')}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="provider">
                                                    Provider
                                                </Label>
                                                <select
                                                    id="provider"
                                                    name="provider"
                                                    defaultValue={
                                                        aiProviderConfiguration.provider
                                                    }
                                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm shadow-sm outline-none transition-colors focus:border-ring focus:ring-1 focus:ring-ring"
                                                >
                                                    {providerOptions.map((option) => (
                                                        <option
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={errors.provider} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="model">
                                                    Model
                                                </Label>
                                                <Input
                                                    id="model"
                                                    name="model"
                                                    defaultValue={aiProviderConfiguration.model ?? ''}
                                                    placeholder={
                                                        aiProviderConfiguration.provider === 'openai'
                                                            ? 'gpt-4.1-mini'
                                                            : aiProviderConfiguration.provider === 'gemini'
                                                                ? 'gemini-2.5-flash'
                                                                : 'llama3.1'
                                                    }
                                                />
                                                <InputError message={errors.model} />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="api_key">
                                                API key
                                            </Label>
                                            <PasswordInput
                                                id="api_key"
                                                name="api_key"
                                                defaultValue={
                                                    aiProviderConfiguration.apiKey ?? ''
                                                }
                                                autoComplete="off"
                                                placeholder={
                                                    aiProviderConfiguration.hasApiKey
                                                        ? 'Deja vacio para conservar la clave actual'
                                                        : 'Pega aqui la clave del proveedor'
                                                }
                                            />
                                            <InputError message={errors.api_key} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="base_url">
                                                Base URL
                                            </Label>
                                            <Input
                                                id="base_url"
                                                name="base_url"
                                                defaultValue={aiProviderConfiguration.baseUrl ?? ''}
                                                placeholder={
                                                    aiProviderConfiguration.isLocal
                                                        ? 'http://localhost:11434'
                                                        : 'Opcional para proveedores externos'
                                                }
                                            />
                                            <InputError message={errors.base_url} />
                                            <p className="text-sm text-muted-foreground">
                                                {aiProviderConfiguration.isLocal
                                                    ? 'Usa esta direccion para conectar con Ollama local.'
                                                    : 'Solo se usa si necesitas una URL personalizada del proveedor.'}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-3 rounded-2xl border bg-muted/20 px-4 py-3">
                                            <Checkbox
                                                id="is_enabled"
                                                name="is_enabled"
                                                defaultChecked={
                                                    aiProviderConfiguration.isEnabled
                                                }
                                                value="1"
                                            />
                                            <Label
                                                htmlFor="is_enabled"
                                                className="font-normal"
                                            >
                                                Activar integracion
                                            </Label>
                                        </div>

                                        <div className="flex flex-wrap gap-3">
                                            <Button disabled={processing}>
                                                Guardar proveedor IA
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <div className="h-1 bg-gradient-to-r from-amber-400 via-orange-400 to-rose-400" />
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl">Connection test</CardTitle>
                                <CardDescription>
                                    Valida que el proveedor pueda responder antes de usarlo en agentes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-2xl border bg-background/80 p-4">
                                    <div className="flex items-center gap-3">
                                        <Server className="h-5 w-5 text-primary" />
                                        <div>
                                            <p className="font-medium">
                                                {aiProviderConfiguration.providerLabel}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Modelo actual: {aiProviderConfiguration.model ?? 'Sin definir'}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Form
                                    action={testConnection.url(teamSlug ?? '')}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Probar conexion
                                        </Button>
                                    )}
                                </Form>

                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <div className="flex items-start gap-3">
                                        <ShieldAlert className="mt-0.5 h-5 w-5 text-amber-500" />
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Si falla la conexion, revisa la guia oficial.
                                            </p>
                                            <Button asChild variant="ghost" className="h-auto px-0 text-sm font-medium">
                                                <a
                                                    href={aiProviderConfiguration.setupUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Abrir guia de instalacion
                                                    <ExternalLink className="ml-2 h-4 w-4" />
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <div className="h-1 bg-gradient-to-r from-sky-500 via-cyan-400 to-emerald-400" />
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl">Quick guides</CardTitle>
                                <CardDescription>
                                    Abrir documentacion segun el proveedor seleccionado.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {providerOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center justify-between gap-3 rounded-2xl border bg-background/80 p-4"
                                    >
                                        <div>
                                            <p className="font-medium">{option.label}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {option.description}
                                            </p>
                                        </div>
                                        <Button asChild variant="ghost" className="h-auto px-0">
                                            <a
                                                href={
                                                    option.value === 'openai'
                                                        ? 'https://platform.openai.com/docs/quickstart/make-your-first-api-request'
                                                        : option.value === 'gemini'
                                                            ? 'https://ai.google.dev/gemini-api/docs/get-started'
                                                            : 'https://www.ollama.com/download'
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Abrir
                                            </a>
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

AiProvider.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'AI provider',
            href: props.currentTeam
                ? aiProviderEdit.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
