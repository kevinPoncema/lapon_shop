import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Loader2, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiClientError, commerceApi, tokenStorage } from '@/lib/api-client';
import { portalPaths } from '@/lib/portal-paths';
import { type PortalShellPageProps } from '@/types/commerce';

export default function TenantAdminLoginPage(props: PortalShellPageProps) {
    const routeCtx = {
        tenantSlug: props.tenantSlug,
        urlMode: props.urlMode,
    };

    const [email, setEmail] = useState<string>('');
    const [password, setPassword] = useState<string>('');
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        setError(null);

        try {
            const res = await commerceApi.admin.login(props.tenantSlug, {
                email: email.trim(),
                password,
                device_name: 'Consola Admin Web',
            });

            tokenStorage.setAdminToken(props.tenantSlug, res.access_token);
            toast.success(`Bienvenido, ${res.user.name}`);
            router.visit(portalPaths.admin.orders(routeCtx));
        } catch (err) {
            if (err instanceof ApiClientError) {
                setError(err.firstFieldError() ?? err.message);
            } else {
                setError('No fue posible conectar con el servidor.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="dark flex min-h-screen flex-col items-center justify-center bg-[#09090b] px-4 text-zinc-100">
            <Head title={`Admin Login · ${props.tenantName}`} />

            <div className="w-full max-w-sm">
                <Link
                    href={portalPaths.storefront.catalog(routeCtx)}
                    className="mb-6 inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-100"
                >
                    <ArrowLeft className="size-3.5" />
                    <span>Volver a la vitrina de {props.tenantName}</span>
                </Link>

                <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-6 shadow-2xl">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-xl border border-indigo-500/30 bg-indigo-500/15 text-indigo-400">
                            <ShieldCheck className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-base font-bold text-zinc-50">
                                Consola Administrativa
                            </h1>
                            <p className="font-mono text-xs text-zinc-500">
                                {props.tenantName} ({props.tenantSlug})
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={(e) => void handleSubmit(e)}
                        className="mt-6 space-y-4"
                    >
                        <div>
                            <Label
                                htmlFor="admin_email"
                                className="text-xs text-zinc-300"
                            >
                                Correo Corporativo
                            </Label>
                            <Input
                                id="admin_email"
                                type="email"
                                required
                                autoComplete="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="admin@tienda.com"
                                className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label
                                htmlFor="admin_password"
                                className="text-xs text-zinc-300"
                            >
                                Contraseña
                            </Label>
                            <Input
                                id="admin_password"
                                type="password"
                                required
                                autoComplete="current-password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="••••••••"
                                className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                            />
                        </div>

                        {error && (
                            <div className="flex items-start gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300">
                                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                <span>{error}</span>
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            className="h-10 w-full bg-indigo-500 text-xs font-semibold text-white hover:bg-indigo-400"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                    <span>Autenticando...</span>
                                </>
                            ) : (
                                'Iniciar Sesión en Tenant'
                            )}
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
