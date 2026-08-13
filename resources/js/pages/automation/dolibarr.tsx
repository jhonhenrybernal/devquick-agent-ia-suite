import { Form, Head, Link } from '@inertiajs/react';
import { Database, ExternalLink, ShieldAlert } from 'lucide-react';
import {
    index as automationIndex,
} from '@/actions/App/Http/Controllers/Automation/AutomationController';
import {
    edit as dolibarrEdit,
    update as updateDolibarr,
} from '@/actions/App/Http/Controllers/Automation/DolibarrController';
import AutomationFlowHeader from '@/components/automation-flow-header';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { DolibarrConfiguration, Team } from '@/types';

type Props = {
    dolibarrConfiguration: DolibarrConfiguration;
    currentTeam?: Team | null;
};

export default function Dolibarr({
    dolibarrConfiguration,
    currentTeam,
}: Props) {
    const teamSlug = currentTeam?.slug;

    return (
        <>
            <Head title="Dolibarr automation" />

            <h1 className="sr-only">Dolibarr automation</h1>

            <div className="relative space-y-8 overflow-hidden">
                <div className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72 bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.14),transparent_42%),radial-gradient(circle_at_top_right,rgba(14,165,233,0.12),transparent_32%)]" />
                <div className="absolute -right-28 top-16 -z-10 size-72 rounded-full bg-emerald-500/10 blur-3xl" />
                <div className="absolute -left-20 top-56 -z-10 size-64 rounded-full bg-cyan-500/10 blur-3xl" />

                <AutomationFlowHeader
                    badges={['Login', 'Password', 'API URL', 'MCP']}
                    title="Dolibarr connection"
                    description="Guarda el login, la contrasena y la URL de tu instancia de Dolibarr."
                    note="Al guardar, el sistema valida las credenciales, obtiene el token de Dolibarr y detecta automaticamente las operaciones disponibles."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={teamSlug ? automationIndex.url(teamSlug) : '#'}>
                                    Volver al hub
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <a
                                    href={dolibarrConfiguration.setupUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Abrir guia
                                    <ExternalLink className="ml-2 h-4 w-4" />
                                </a>
                            </Button>
                        </>
                    }
                    stages={[
                        {
                            label: 'Login',
                            description: dolibarrConfiguration.hasApiLogin
                                ? 'Usuario de API configurado.'
                                : 'Necesario para autenticar la sesion REST.',
                            active: dolibarrConfiguration.hasApiLogin,
                        },
                        {
                            label: 'Password',
                            description: dolibarrConfiguration.hasApiPassword
                                ? 'Password guardado de forma cifrada.'
                                : 'Completa la clave de acceso para Dolibarr.',
                            active: dolibarrConfiguration.hasApiPassword,
                        },
                        {
                            label: 'API URL',
                            description: dolibarrConfiguration.hasApiUrl
                                ? `URL base configurada: ${dolibarrConfiguration.apiUrl ?? ''}`
                                : 'Apunta a tu instancia o al explorador REST.',
                            active: dolibarrConfiguration.hasApiUrl,
                        },
                        {
                            label: 'Explore endpoints',
                            description: 'El barrido de endpoints se ejecuta automaticamente cuando la conexion es valida.',
                            active: dolibarrConfiguration.discoveredApiCount > 0,
                        },
                    ]}
                />

                <div className="grid gap-4 xl:grid-cols-[1.25fr_0.75fr]">
                    <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                        <div className="h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-400" />
                        <CardHeader className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="flex items-center gap-2 text-xl">
                                    <span className="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                        <Database className="h-5 w-5" />
                                    </span>
                                    Dolibarr configuration
                                </CardTitle>
                                <Badge
                                    variant={
                                        dolibarrConfiguration.hasApiLogin && dolibarrConfiguration.hasApiPassword && dolibarrConfiguration.hasApiUrl
                                            ? 'default'
                                            : 'secondary'
                                    }
                                    className="rounded-full px-3 py-1"
                                >
                                    {dolibarrConfiguration.hasApiLogin && dolibarrConfiguration.hasApiPassword && dolibarrConfiguration.hasApiUrl
                                        ? 'Configurado'
                                        : 'Pendiente'}
                                </Badge>
                            </div>
                            <CardDescription className="max-w-md">
                                El password se guarda cifrado y la URL se usa para apuntar a tu instancia o al explorador REST.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...updateDolibarr.form(teamSlug ?? '')}
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="api_login">
                                                    Login
                                                </Label>
                                                <Input
                                                    id="api_login"
                                                    name="api_login"
                                                    defaultValue={dolibarrConfiguration.apiLogin ?? ''}
                                                    autoComplete="off"
                                                    placeholder="usuario.api"
                                                />
                                                <InputError message={errors.api_login} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="api_password">
                                                    Password
                                                </Label>
                                                <PasswordInput
                                                    id="api_password"
                                                    name="api_password"
                                                    defaultValue={dolibarrConfiguration.apiPassword ?? ''}
                                                    autoComplete="new-password"
                                                    placeholder="Escribe la contrasena de Dolibarr"
                                                />
                                                <InputError message={errors.api_password} />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="api_url">
                                                API URL
                                            </Label>
                                            <Input
                                                id="api_url"
                                                name="api_url"
                                                type="url"
                                                defaultValue={dolibarrConfiguration.apiUrl ?? ''}
                                                autoComplete="off"
                                                placeholder="https://suite.devquick.co/api/index.php/explorer/"
                                            />
                                            <InputError message={errors.api_url} />
                                        </div>

                                        <p className="text-sm text-muted-foreground">
                                            La validacion y el barrido de endpoints se ejecutan automaticamente antes de guardar. Por seguridad, el password no se muestra al volver a editar.
                                        </p>

                                        <div className="flex flex-wrap gap-3">
                                            <Button disabled={processing}>
                                                Guardar y detectar
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <div className="h-1 bg-gradient-to-r from-cyan-500 via-sky-400 to-emerald-400" />
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl">Endpoints detectados</CardTitle>
                                <CardDescription>
                                    El barrido de la API se actualiza automaticamente cuando guardas una configuracion valida.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-2xl border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                            Login
                                        </p>
                                        <p className="mt-2 text-base font-medium">
                                            {dolibarrConfiguration.hasApiLogin ? 'Configurado' : 'Pendiente'}
                                        </p>
                                    </div>
                                    <div className="rounded-2xl border bg-muted/20 p-4">
                                        <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                            Password
                                        </p>
                                        <p className="mt-2 text-base font-medium">
                                            {dolibarrConfiguration.hasApiPassword ? 'Guardado' : 'Pendiente'}
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                        Detectados
                                    </p>
                                    <p className="mt-2 text-base font-medium">
                                        {dolibarrConfiguration.discoveredApiCount}
                                    </p>
                                    <p className="mt-2 text-sm font-medium text-muted-foreground">
                                        Flujo inicial para facturas
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {dolibarrConfiguration.importantApis.map((api) => (
                                            <Badge key={api} variant="secondary" className="rounded-full px-3 py-1">
                                                {api}
                                            </Badge>
                                        ))}
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        {dolibarrConfiguration.lastDiscoveredAt
                                            ? `Ultima deteccion: ${new Date(dolibarrConfiguration.lastDiscoveredAt).toLocaleString()}`
                                            : 'Aun no se ha ejecutado un guardado valido.'}
                                    </p>
                                </div>

                                <div className="rounded-2xl border bg-background/80 p-4">
                                    <p className="text-sm font-medium">
                                        Operaciones detectadas
                                    </p>
                                    {dolibarrConfiguration.discoveredApis.length > 0 ? (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {dolibarrConfiguration.discoveredApis.map((api) => (
                                                <Badge key={api} variant="secondary" className="rounded-full px-3 py-1">
                                                    {api}
                                                </Badge>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Guarda la configuracion para explorar y listar automaticamente las operaciones disponibles.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-2xl border bg-muted/20 p-4">
                                    <div className="flex items-start gap-3">
                                        <ShieldAlert className="mt-0.5 h-5 w-5 text-amber-500" />
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Si Dolibarr no responde, revisa que la API REST este activa y que la URL apunte a la instancia correcta.
                                            </p>
                                            <Button asChild variant="ghost" className="h-auto px-0 text-sm font-medium">
                                                <a
                                                    href={dolibarrConfiguration.setupUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Abrir guia oficial
                                                    <ExternalLink className="ml-2 h-4 w-4" />
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="overflow-hidden border-border/70 bg-card/90 shadow-sm">
                            <div className="h-1 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-400" />
                            <CardHeader className="space-y-2">
                                <CardTitle className="text-xl">Siguiente paso</CardTitle>
                                <CardDescription>
                                    Cuando esto quede conectado, los agentes llamaran a tools MCP como get_customers, search_products y create_invoice.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p className="text-sm text-muted-foreground">
                                    Esta pantalla solo prepara la credencial base. La logica de negocio vivira en tools MCP para no acoplar los agentes a Dolibarr directamente.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Dolibarr.layout = (props: { currentTeam?: Team | null }) => ({
    breadcrumbs: [
        {
            title: 'Automation',
            href: props.currentTeam
                ? automationIndex.url(props.currentTeam.slug)
                : '/',
        },
        {
            title: 'Dolibarr',
            href: props.currentTeam
                ? dolibarrEdit.url(props.currentTeam.slug)
                : '/',
        },
    ],
});
