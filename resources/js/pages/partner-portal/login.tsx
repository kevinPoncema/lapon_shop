import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Loader2, QrCode } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiClientError, commerceApi, tokenStorage } from '@/lib/api-client';
import { portalPaths } from '@/lib/portal-paths';
import { type PortalShellPageProps } from '@/types/commerce';

export default function PartnerPortalLoginPage(props: PortalShellPageProps) {
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
            const res = await commerceApi.partner.login(props.tenantSlug, {
                email: email.trim(),
                password,
                device_name: 'Terminal POS Aliado',
            });
            tokenStorage.setPartnerToken(props.tenantSlug, res.access_token);
            toast.success(`Terminal activa: ${res.partner_store.name}`);
            router.visit(portalPaths.partner.pos(routeCtx));
        } catch (err) {
            if (err instanceof ApiClientError) {
                setError(err.firstFieldError() ?? err.message);
            } else {
                setError('No fue posible autenticar la terminal aliada.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="dark flex min-h-screen flex-col items-center justify-center bg-[#09090b] px-4 text-zinc-100">
            <Head title={`POS Comercio Aliado · ${props.tenantName}`} />

            <div className="w-full max-w-sm">
                <Link
                    href={portalPaths.storefront.catalog(routeCtx)}
                    className="mb-6 inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-100"
                >
                    <ArrowLeft className="size-3.5" />
                    <span>Volver a {props.tenantName}</span>
                </Link>

                <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-6 shadow-2xl">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500/15 text-emerald-400">
                            <QrCode className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-base font-bold text-zinc-50">
                                Portal POS de Aliados
                            </h1>
                            <p className="font-mono text-xs text-zinc-500">
                                Verificación de Vouchers · {props.tenantName}
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={(e) => void handleSubmit(e)}
                        className="mt-6 space-y-4"
                    >
                        <div>
                            <Label
                                htmlFor="partner_email"
                                className="text-xs text-zinc-300"
                            >
                                Correo de Caja / Sucursal Aliada
                            </Label>
                            <Input
                                id="partner_email"
                                type="email"
                                required
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="caja@comercio-aliado.com"
                                className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                            />
                        </div>

                        <div>
                            <Label
                                htmlFor="partner_password"
                                className="text-xs text-zinc-300"
                            >
                                Contraseña POS
                            </Label>
                            <Input
                                id="partner_password"
                                type="password"
                                required
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
                            className="h-11 w-full bg-emerald-500 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 size-4 animate-spin" />
                                    <span>Conectando caja...</span>
                                </>
                            ) : (
                                'Abrir Terminal de Canje'
                            )}
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
