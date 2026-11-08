import { Link, router } from '@inertiajs/react';
import { Loader2, LogOut, QrCode, Store } from 'lucide-react';
import { type ReactNode, useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ApiClientError, commerceApi, tokenStorage } from '@/lib/api-client';
import { portalPaths, type PortalUrlMode } from '@/lib/portal-paths';
import { type PartnerMeResponse } from '@/types/commerce';

export interface PartnerPosLayoutProps {
    tenantSlug: string;
    tenantName: string;
    urlMode: PortalUrlMode;
    children: (session: PartnerMeResponse) => ReactNode;
}

export function PartnerPosLayout({
    tenantSlug,
    tenantName,
    urlMode,
    children,
}: PartnerPosLayoutProps) {
    const routeCtx = { tenantSlug, urlMode };
    const [session, setSession] = useState<PartnerMeResponse | null>(null);
    const [isChecking, setIsChecking] = useState<boolean>(true);

    useEffect(() => {
        const token = tokenStorage.getPartnerToken(tenantSlug);
        if (!token) {
            router.visit(portalPaths.partner.login(routeCtx), {
                replace: true,
            });
            return;
        }

        const controller = new AbortController();
        setIsChecking(true);

        void (async () => {
            try {
                const res = await commerceApi.partner.me(
                    tenantSlug,
                    controller.signal,
                );
                setSession(res);
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                if (err instanceof ApiClientError) {
                    tokenStorage.clearPartnerToken(tenantSlug);
                } else {
                    tokenStorage.clearPartnerToken(tenantSlug);
                }
                router.visit(portalPaths.partner.login(routeCtx), {
                    replace: true,
                });
            } finally {
                if (!controller.signal.aborted) {
                    setIsChecking(false);
                }
            }
        })();

        return () => controller.abort();
    }, [tenantSlug, urlMode]);

    const handleLogout = useCallback(async () => {
        try {
            await commerceApi.partner.logout(tenantSlug);
        } catch {
            // Ignore
        } finally {
            tokenStorage.clearPartnerToken(tenantSlug);
            router.visit(portalPaths.partner.login(routeCtx), {
                replace: true,
            });
        }
    }, [tenantSlug, urlMode]);

    if (isChecking || !session) {
        return (
            <div className="dark flex min-h-screen items-center justify-center bg-[#09090b] text-zinc-300">
                <div className="flex items-center gap-2 font-mono text-xs">
                    <Loader2 className="size-4 animate-spin text-emerald-400" />
                    <span>Conectando terminal POS aliado...</span>
                </div>
            </div>
        );
    }

    return (
        <div className="dark flex min-h-screen flex-col bg-[#09090b] text-zinc-100">
            <header className="border-b border-zinc-800 bg-[#0d0d10]">
                <div className="mx-auto flex h-16 max-w-xl items-center justify-between px-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-9 items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500/15 text-emerald-400">
                            <QrCode className="size-4" />
                        </div>
                        <div>
                            <p className="text-sm font-bold text-zinc-50">
                                {session.partner_store.name}
                            </p>
                            <p className="font-mono text-[11px] text-zinc-500">
                                Red Aliada ·{' '}
                                {session.origin_tenant.name || tenantName}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-1.5">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="size-8 text-zinc-400 hover:text-zinc-100"
                            title="Ir a la tienda origen"
                        >
                            <Link
                                href={portalPaths.storefront.catalog(routeCtx)}
                            >
                                <Store className="size-4" />
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => void handleLogout()}
                            className="h-8 px-2.5 text-xs text-zinc-400 hover:text-rose-300"
                        >
                            <LogOut className="mr-1.5 size-3.5" />
                            Salir
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-xl flex-1 px-4 py-6">
                {children(session)}
            </main>
        </div>
    );
}
