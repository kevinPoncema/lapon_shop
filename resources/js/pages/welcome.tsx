import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Layers,
    MessageCircle,
    QrCode,
    ShieldCheck,
    Sparkles,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { portalPaths } from '@/lib/portal-paths';

interface TenantDirectoryItem {
    id: string;
    name: string;
    slug: string;
    country_code: string;
    currency_code: string;
}

interface WelcomePageProps {
    tenants?: TenantDirectoryItem[];
}

export default function Welcome({ tenants = [] }: WelcomePageProps) {
    const [manualSlug, setManualSlug] = useState<string>('');

    const handleJumpToTenant = (e: React.FormEvent) => {
        e.preventDefault();
        const slug = manualSlug
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-]/g, '');
        if (!slug) {
            return;
        }
        router.visit(
            portalPaths.storefront.catalog({
                tenantSlug: slug,
                urlMode: 'path',
            }),
        );
    };

    return (
        <div className="dark min-h-screen bg-[#09090b] text-zinc-100">
            <Head title="CommerceHub SaaS · Multi-Tenant Schema-Driven Commerce" />

            <div className="relative overflow-hidden border-b border-zinc-800/80 bg-[#0d0d10]">
                <div className="pointer-events-none absolute -top-32 left-1/2 size-[520px] -translate-x-1/2 rounded-full bg-indigo-500/15 blur-3xl" />

                <div className="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 font-mono text-xs text-indigo-300">
                        <Sparkles className="size-3.5" />
                        <span>Obsidian Kinetic · Multi-Tenant Engine v1</span>
                    </div>

                    <h1 className="mt-4 max-w-3xl text-3xl font-bold tracking-tight text-zinc-50 sm:text-5xl">
                        Comercio electrónico{' '}
                        <span className="text-indigo-400">Schema-Driven</span>{' '}
                        con cierre asistido en WhatsApp.
                    </h1>

                    <p className="mt-4 max-w-2xl text-sm leading-relaxed text-zinc-400 sm:text-base">
                        Explora los tres portales desacoplados del ecosistema:
                        Vitrina Pública sin fricción de registro, Consola
                        Operativa del Comercio y Portal POS Móvil para canje de
                        recompensas cruzadas.
                    </p>

                    <form
                        onSubmit={handleJumpToTenant}
                        className="mt-8 flex max-w-md gap-2"
                    >
                        <div className="relative flex-1">
                            <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 font-mono text-xs text-zinc-500">
                                /t/
                            </span>
                            <Input
                                value={manualSlug}
                                onChange={(e) => setManualSlug(e.target.value)}
                                placeholder="kinetic-wear"
                                className="h-10 border-zinc-800 bg-[#121215] pl-9 font-mono text-xs text-zinc-100"
                            />
                        </div>
                        <Button
                            type="submit"
                            className="h-10 gap-1.5 bg-indigo-500 px-4 text-xs font-semibold text-white hover:bg-indigo-400"
                        >
                            <span>Abrir Tienda</span>
                            <ArrowRight className="size-3.5" />
                        </Button>
                    </form>
                </div>
            </div>

            <main className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                {/* Pilares del Sistema */}
                <div className="mb-12 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-zinc-800 bg-[#121215] p-5">
                        <MessageCircle className="size-5 text-emerald-400" />
                        <h2 className="mt-3 text-sm font-semibold text-zinc-100">
                            1. Storefront Público (Guest-First)
                        </h2>
                        <p className="mt-1 text-xs leading-relaxed text-zinc-400">
                            Filtros facetados generados desde{' '}
                            <code className="font-mono text-zinc-300">
                                filter_config
                            </code>
                            , cotización reactiva en servidor y checkout
                            idempotente hacia WhatsApp sin login.
                        </p>
                    </div>

                    <div className="rounded-xl border border-zinc-800 bg-[#121215] p-5">
                        <Layers className="size-5 text-indigo-400" />
                        <h2 className="mt-3 text-sm font-semibold text-zinc-100">
                            2. Consola Admin del Tenant
                        </h2>
                        <p className="mt-1 text-xs leading-relaxed text-zinc-400">
                            Constructor de{' '}
                            <code className="font-mono text-zinc-300">
                                metadata_schema
                            </code>
                            , gestión de stock por variante, centro de órdenes y
                            liquidación de comisiones de afiliados.
                        </p>
                    </div>

                    <div className="rounded-xl border border-zinc-800 bg-[#121215] p-5">
                        <QrCode className="size-5 text-amber-400" />
                        <h2 className="mt-3 text-sm font-semibold text-zinc-100">
                            3. Portal POS de Comercios Aliados
                        </h2>
                        <p className="mt-1 text-xs leading-relaxed text-zinc-400">
                            Sesión Sanctum aislada (
                            <code className="font-mono text-zinc-300">
                                partner-portal
                            </code>
                            ) para verificar y canjear vouchers de recompensas
                            cruzadas en mostrador.
                        </p>
                    </div>
                </div>

                {/* Directorio de Tenants Activos */}
                <div className="flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-semibold tracking-wider text-zinc-300 uppercase">
                        <Store className="size-4 text-indigo-400" />
                        <span>Inquilinos Activos ({tenants.length})</span>
                    </h2>
                </div>

                {tenants.length === 0 ? (
                    <div className="mt-4 rounded-2xl border border-dashed border-zinc-800 bg-[#121215]/60 p-8 text-center">
                        <p className="text-sm font-medium text-zinc-200">
                            Aún no hay tiendas activas sembradas en la base de
                            datos.
                        </p>
                        <p className="mt-1 font-mono text-xs text-zinc-500">
                            Ejecuta el seeder de demostración para poblar una
                            tienda completa con catálogo, cupones y aliados.
                        </p>
                    </div>
                ) : (
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {tenants.map((t) => {
                            const ctx = {
                                tenantSlug: t.slug,
                                urlMode: 'path' as const,
                            };
                            return (
                                <div
                                    key={t.id}
                                    className="flex flex-col justify-between rounded-xl border border-zinc-800 bg-[#121215] p-5 transition hover:border-zinc-700"
                                >
                                    <div>
                                        <div className="flex items-center justify-between gap-2">
                                            <h3 className="text-base font-semibold text-zinc-50">
                                                {t.name}
                                            </h3>
                                            <span className="rounded border border-zinc-800 bg-zinc-900 px-2 py-0.5 font-mono text-[10px] text-zinc-400">
                                                {t.country_code} ·{' '}
                                                {t.currency_code}
                                            </span>
                                        </div>
                                        <p className="mt-1 font-mono text-xs text-zinc-500">
                                            /t/{t.slug}
                                        </p>
                                    </div>

                                    <div className="mt-5 space-y-2 border-t border-zinc-800/80 pt-4">
                                        <Button
                                            asChild
                                            className="h-9 w-full justify-between bg-indigo-500 text-xs font-semibold text-white hover:bg-indigo-400"
                                        >
                                            <Link
                                                href={portalPaths.storefront.catalog(
                                                    ctx,
                                                )}
                                            >
                                                <span>
                                                    Abrir Vitrina Pública
                                                </span>
                                                <ArrowRight className="size-3.5" />
                                            </Link>
                                        </Button>

                                        <div className="grid grid-cols-2 gap-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="h-8 border-zinc-800 bg-zinc-950 text-xs text-zinc-300 hover:text-zinc-100"
                                            >
                                                <Link
                                                    href={portalPaths.admin.orders(
                                                        ctx,
                                                    )}
                                                >
                                                    <ShieldCheck className="mr-1.5 size-3.5 text-indigo-400" />
                                                    Admin
                                                </Link>
                                            </Button>

                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="h-8 border-zinc-800 bg-zinc-950 text-xs text-zinc-300 hover:text-zinc-100"
                                            >
                                                <Link
                                                    href={portalPaths.partner.pos(
                                                        ctx,
                                                    )}
                                                >
                                                    <QrCode className="mr-1.5 size-3.5 text-emerald-400" />
                                                    POS Aliado
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </main>
        </div>
    );
}
