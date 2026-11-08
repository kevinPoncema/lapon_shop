import { Link } from '@inertiajs/react';
import {
    Boxes,
    ExternalLink,
    Handshake,
    Loader2,
    LogOut,
    Menu,
    MessageSquareText,
    Sliders,
    Tag,
    Users,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { AdminAuthProvider, useAdminAuth } from '@/contexts/admin-auth-context';
import { TenantProvider, useTenant } from '@/contexts/tenant-context';
import { portalPaths, type PortalUrlMode } from '@/lib/portal-paths';

export type AdminNavSection =
    | 'orders'
    | 'catalog'
    | 'schema'
    | 'marketing'
    | 'affiliates'
    | 'partners';

interface AdminShellInnerProps {
    activeSection: AdminNavSection;
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
}

function AdminShellInner({
    activeSection,
    title,
    subtitle,
    actions,
    children,
}: AdminShellInnerProps) {
    const { tenantName, tenantSlug, currencyCode, routeCtx } = useTenant();
    const { user, isCheckingAuth, logout } = useAdminAuth();
    const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);

    if (isCheckingAuth || !user) {
        return (
            <div className="dark flex min-h-screen items-center justify-center bg-[#09090b] text-zinc-300">
                <div className="flex items-center gap-2.5 font-mono text-xs">
                    <Loader2 className="size-4 animate-spin text-indigo-400" />
                    <span>Verificando credenciales del tenant...</span>
                </div>
            </div>
        );
    }

    const navItems: Array<{
        key: AdminNavSection;
        label: string;
        href: string;
        icon: typeof MessageSquareText;
    }> = [
        {
            key: 'orders',
            label: 'Órdenes WhatsApp',
            href: portalPaths.admin.orders(routeCtx),
            icon: MessageSquareText,
        },
        {
            key: 'catalog',
            label: 'Catálogo y Variantes',
            href: portalPaths.admin.catalog(routeCtx),
            icon: Boxes,
        },
        {
            key: 'schema',
            label: 'Esquema y Config',
            href: portalPaths.admin.schema(routeCtx),
            icon: Sliders,
        },
        {
            key: 'marketing',
            label: 'Promos y Cupones',
            href: portalPaths.admin.marketing(routeCtx),
            icon: Tag,
        },
        {
            key: 'affiliates',
            label: 'Afiliados y Comisiones',
            href: portalPaths.admin.affiliates(routeCtx),
            icon: Users,
        },
        {
            key: 'partners',
            label: 'Comercios Aliados',
            href: portalPaths.admin.partners(routeCtx),
            icon: Handshake,
        },
    ];

    const renderNavContent = () => (
        <div className="flex h-full flex-col justify-between">
            <div>
                <div className="border-b border-zinc-800/80 px-4 py-4">
                    <div className="flex items-center justify-between">
                        <span className="truncate text-sm font-bold text-zinc-50">
                            {tenantName}
                        </span>
                        <span className="rounded border border-zinc-800 bg-zinc-900 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400">
                            {currencyCode}
                        </span>
                    </div>
                    <p className="mt-0.5 font-mono text-[11px] text-zinc-500">
                        tenant:{tenantSlug}
                    </p>
                </div>

                <nav className="space-y-1 p-3">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const active = item.key === activeSection;
                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                onClick={() => setMobileMenuOpen(false)}
                                className={`flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
                                    active
                                        ? 'bg-primary/15 text-zinc-50 ring-1 ring-primary/40'
                                        : 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100'
                                }`}
                            >
                                <Icon
                                    className={`size-4 ${
                                        active
                                            ? 'text-primary'
                                            : 'text-zinc-500'
                                    }`}
                                />
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>
            </div>

            <div className="space-y-3 border-t border-zinc-800/80 p-3">
                <Link
                    href={portalPaths.storefront.catalog(routeCtx)}
                    className="flex items-center justify-between rounded-lg border border-zinc-800 bg-zinc-900/80 px-3 py-2 text-xs text-zinc-300 transition hover:border-zinc-700 hover:text-zinc-50"
                >
                    <span>Ver Vitrina Pública</span>
                    <ExternalLink className="size-3.5 text-zinc-500" />
                </Link>

                <div className="rounded-lg border border-zinc-800/80 bg-[#121215] p-2.5">
                    <div className="flex items-center justify-between gap-2">
                        <p className="truncate text-xs font-medium text-zinc-200">
                            {user.name}
                        </p>
                        <span className="rounded bg-indigo-500/15 px-1.5 py-0.5 font-mono text-[9px] font-semibold text-indigo-300">
                            {user.role}
                        </span>
                    </div>
                    <p className="truncate font-mono text-[10px] text-zinc-500">
                        {user.email}
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => void logout()}
                        className="mt-2 h-7 w-full justify-start px-2 text-xs text-zinc-400 hover:bg-rose-500/10 hover:text-rose-300"
                    >
                        <LogOut className="mr-1.5 size-3.5" />
                        Cerrar sesión
                    </Button>
                </div>
            </div>
        </div>
    );

    return (
        <div className="dark flex min-h-screen bg-[#09090b] text-zinc-100">
            {/* Desktop Sidebar */}
            <aside className="hidden w-64 shrink-0 border-r border-zinc-800/90 bg-[#0d0d10] lg:block">
                <div className="sticky top-0 h-screen">
                    {renderNavContent()}
                </div>
            </aside>

            {/* Main Content */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-zinc-800/80 bg-[#09090b]/90 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => setMobileMenuOpen(true)}
                            className="size-8 border-zinc-800 bg-[#121215] lg:hidden"
                        >
                            <Menu className="size-4" />
                        </Button>
                        <div className="min-w-0">
                            <h1 className="truncate text-base font-semibold tracking-tight text-zinc-50 sm:text-lg">
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="hidden truncate text-xs text-zinc-400 sm:block">
                                    {subtitle}
                                </p>
                            )}
                        </div>
                    </div>

                    {actions && (
                        <div className="flex items-center gap-2">{actions}</div>
                    )}
                </header>

                <main className="flex-1 p-4 sm:p-6 lg:p-8">{children}</main>
            </div>

            {/* Mobile Sidebar */}
            <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                <SheetContent
                    side="left"
                    className="w-64 border-r border-zinc-800 bg-[#0d0d10] p-0 text-zinc-100"
                >
                    <SheetHeader className="sr-only">
                        <SheetTitle>Menú Administrativo</SheetTitle>
                    </SheetHeader>
                    {renderNavContent()}
                </SheetContent>
            </Sheet>
        </div>
    );
}

export interface TenantAdminLayoutProps {
    tenantSlug: string;
    tenantName: string;
    currencyCode: string;
    countryCode: string;
    urlMode: PortalUrlMode;
    activeSection: AdminNavSection;
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
}

export function TenantAdminLayout({
    tenantSlug,
    tenantName,
    currencyCode,
    countryCode,
    urlMode,
    activeSection,
    title,
    subtitle,
    actions,
    children,
}: TenantAdminLayoutProps) {
    return (
        <TenantProvider
            tenantSlug={tenantSlug}
            tenantName={tenantName}
            currencyCode={currencyCode}
            countryCode={countryCode}
            urlMode={urlMode}
        >
            <AdminAuthProvider>
                <AdminShellInner
                    activeSection={activeSection}
                    title={title}
                    subtitle={subtitle}
                    actions={actions}
                >
                    {children}
                </AdminShellInner>
            </AdminAuthProvider>
        </TenantProvider>
    );
}
