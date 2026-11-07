import { Link } from '@inertiajs/react';
import {
    Building2,
    QrCode,
    ShieldCheck,
    ShoppingBag,
    Store,
} from 'lucide-react';
import { type ReactNode } from 'react';
import { CartQuoteDrawer } from '@/components/storefront/cart-quote-drawer';
import { Button } from '@/components/ui/button';
import { CartProvider, useCart } from '@/contexts/cart-context';
import { TenantProvider, useTenant } from '@/contexts/tenant-context';
import { formatMoney } from '@/lib/formatters';
import { portalPaths, type PortalUrlMode } from '@/lib/portal-paths';

interface StorefrontShellInnerProps {
    children: ReactNode;
}

function StorefrontShellInner({ children }: StorefrontShellInnerProps) {
    const {
        tenantName,
        tenantSlug,
        currencyCode,
        countryCode,
        routeCtx,
        bootstrap,
    } = useTenant();
    const { totalQuantity, quote, setDrawerOpen } = useCart();

    const logoUrl = bootstrap?.branding.logo_url;
    const socialLinks = bootstrap?.branding.social_links;
    const initials = tenantName
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0] ?? '')
        .join('')
        .toUpperCase();

    return (
        <div className="dark flex min-h-screen flex-col bg-[#09090b] text-zinc-100">
            <header className="sticky top-0 z-40 border-b border-zinc-800/80 bg-[#09090b]/85 backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-4">
                        <Link
                            href={portalPaths.storefront.catalog(routeCtx)}
                            className="flex items-center gap-3 transition hover:opacity-90"
                        >
                            {logoUrl ? (
                                <img
                                    src={logoUrl}
                                    alt={tenantName}
                                    className="size-9 rounded-lg border border-zinc-800 object-cover"
                                />
                            ) : (
                                <div className="flex size-9 items-center justify-center rounded-lg border border-primary/40 bg-primary/15 font-mono text-xs font-bold text-primary">
                                    {initials || 'CH'}
                                </div>
                            )}
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-semibold tracking-tight text-zinc-50 sm:text-base">
                                        {tenantName}
                                    </span>
                                    <span className="hidden rounded border border-zinc-800 bg-zinc-900 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400 sm:inline-block">
                                        {countryCode} · {currencyCode}
                                    </span>
                                </div>
                                <p className="hidden font-mono text-[11px] text-zinc-500 sm:block">
                                    /{tenantSlug} · Vitrina Directa WhatsApp
                                </p>
                            </div>
                        </Link>
                    </div>

                    <div className="flex items-center gap-2 sm:gap-3">
                        <div className="hidden items-center gap-1 rounded-lg border border-zinc-800/90 bg-[#121215] p-1 md:flex">
                            <Link
                                href={portalPaths.home()}
                                className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] text-zinc-400 transition hover:bg-zinc-800/80 hover:text-zinc-100"
                                title="Directorio de Tiendas"
                            >
                                <Store className="size-3" />
                                <span>Tiendas</span>
                            </Link>
                            <Link
                                href={portalPaths.admin.orders(routeCtx)}
                                className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] text-zinc-400 transition hover:bg-zinc-800/80 hover:text-zinc-100"
                                title="Panel Administrativo del Tenant"
                            >
                                <ShieldCheck className="size-3 text-primary" />
                                <span>Admin</span>
                            </Link>
                            <Link
                                href={portalPaths.partner.pos(routeCtx)}
                                className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-[11px] text-zinc-400 transition hover:bg-zinc-800/80 hover:text-zinc-100"
                                title="Portal POS de Comercio Aliado"
                            >
                                <QrCode className="size-3 text-emerald-400" />
                                <span>POS Aliado</span>
                            </Link>
                        </div>

                        <Button
                            type="button"
                            onClick={() => setDrawerOpen(true)}
                            className="h-9 gap-2 border border-zinc-700/80 bg-[#18181b] px-3 text-xs font-medium text-zinc-100 hover:border-primary/60 hover:bg-zinc-800"
                        >
                            <ShoppingBag className="size-4 text-primary" />
                            <span className="hidden sm:inline">Bolsa</span>
                            <span className="rounded-full bg-primary px-1.5 py-0.5 font-mono text-[11px] font-bold text-white">
                                {totalQuantity}
                            </span>
                            {quote && totalQuantity > 0 && (
                                <span className="hidden border-l border-zinc-700 pl-2 font-mono text-xs text-emerald-400 sm:inline">
                                    {formatMoney(
                                        quote.summary.total_net,
                                        currencyCode,
                                    )}
                                </span>
                            )}
                        </Button>
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="mt-16 border-t border-zinc-800/80 bg-[#0d0d10]">
                <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-xs text-zinc-500 sm:flex-row sm:px-6 lg:px-8">
                    <div className="flex items-center gap-2">
                        <Building2 className="size-3.5 text-zinc-600" />
                        <span>
                            <strong className="font-medium text-zinc-300">
                                {tenantName}
                            </strong>{' '}
                            · Checkout asistido sin registro
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-4">
                        {socialLinks?.instagram && (
                            <a
                                href={socialLinks.instagram}
                                target="_blank"
                                rel="noreferrer"
                                className="hover:text-zinc-300"
                            >
                                Instagram
                            </a>
                        )}
                        {socialLinks?.facebook && (
                            <a
                                href={socialLinks.facebook}
                                target="_blank"
                                rel="noreferrer"
                                className="hover:text-zinc-300"
                            >
                                Facebook
                            </a>
                        )}
                        {socialLinks?.tiktok && (
                            <a
                                href={socialLinks.tiktok}
                                target="_blank"
                                rel="noreferrer"
                                className="hover:text-zinc-300"
                            >
                                TikTok
                            </a>
                        )}
                        <Link
                            href={portalPaths.admin.orders(routeCtx)}
                            className="hover:text-zinc-300 md:hidden"
                        >
                            Admin
                        </Link>
                        <Link
                            href={portalPaths.partner.pos(routeCtx)}
                            className="hover:text-zinc-300 md:hidden"
                        >
                            POS Aliado
                        </Link>
                    </div>
                </div>
            </footer>

            <CartQuoteDrawer />
        </div>
    );
}

export interface StorefrontLayoutProps {
    tenantSlug: string;
    tenantName: string;
    currencyCode: string;
    countryCode: string;
    urlMode: PortalUrlMode;
    children: ReactNode;
}

export function StorefrontLayout({
    tenantSlug,
    tenantName,
    currencyCode,
    countryCode,
    urlMode,
    children,
}: StorefrontLayoutProps) {
    return (
        <TenantProvider
            tenantSlug={tenantSlug}
            tenantName={tenantName}
            currencyCode={currencyCode}
            countryCode={countryCode}
            urlMode={urlMode}
            includeCategories
        >
            <CartProvider>
                <StorefrontShellInner>{children}</StorefrontShellInner>
            </CartProvider>
        </TenantProvider>
    );
}
