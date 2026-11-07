import { Head, Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Package,
    Search,
    ShoppingBag,
    SlidersHorizontal,
    Sparkles,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    type ActiveCatalogFilters,
    FacetedFilterSidebar,
} from '@/components/storefront/faceted-filter-sidebar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useCart } from '@/contexts/cart-context';
import { useTenant } from '@/contexts/tenant-context';
import { StorefrontLayout } from '@/layouts/storefront-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import {
    formatMoney,
    formatVariantAttributes,
    stringifyMetadataValue,
} from '@/lib/formatters';
import { portalPaths } from '@/lib/portal-paths';
import {
    type PaginationMeta,
    type PortalShellPageProps,
    type StorefrontMatchedVariant,
    type StorefrontProductCard,
    type StorefrontProductQuery,
    type StorefrontProductSort,
} from '@/types/commerce';

const INITIAL_FILTERS: ActiveCatalogFilters = {
    categorySlug: '',
    priceMin: '',
    priceMax: '',
    inStock: false,
    attr: {},
};

function ProductCardItem({ product }: { product: StorefrontProductCard }) {
    const { currencyCode, routeCtx } = useTenant();
    const { addItem } = useCart();

    const defaultVariant =
        product.matched_variants.find((v) => v.is_default && v.stock > 0) ??
        product.matched_variants.find((v) => v.stock > 0) ??
        product.matched_variants[0] ??
        null;

    const [selectedVariantId, setSelectedVariantId] = useState<string>(
        defaultVariant?.id ?? '',
    );

    const activeVariant: StorefrontMatchedVariant | null =
        product.matched_variants.find((v) => v.id === selectedVariantId) ??
        defaultVariant;

    const totalStock = product.matched_variants.reduce(
        (acc, v) => acc + v.stock,
        0,
    );
    const displayPrice = activeVariant?.effective_price ?? product.base_price;
    const canQuickAdd = activeVariant !== null && activeVariant.stock > 0;

    const metadataEntries = Object.entries(product.metadata ?? {}).slice(0, 2);

    const handleQuickAdd = () => {
        if (!activeVariant || activeVariant.stock <= 0) {
            return;
        }

        addItem({
            variantId: activeVariant.id,
            productName: product.name,
            productSlug: product.slug,
            sku: activeVariant.sku,
            unitPrice: activeVariant.effective_price,
            image: product.images[0] ?? null,
            variantLabel: formatVariantAttributes(activeVariant.metadata),
        });
    };

    return (
        <article className="group flex flex-col overflow-hidden rounded-xl border border-zinc-800/90 bg-[#121215] transition duration-200 hover:border-zinc-700">
            <Link
                href={portalPaths.storefront.product(routeCtx, product.slug)}
                className="relative aspect-[4/3] w-full overflow-hidden bg-zinc-900"
            >
                {product.images[0] ? (
                    <img
                        src={product.images[0]}
                        alt={product.name}
                        className="size-full object-cover transition duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-zinc-900 to-zinc-950 text-zinc-600">
                        <Package className="size-8" />
                        <span className="font-mono text-[10px] tracking-widest uppercase">
                            {activeVariant?.sku ?? 'CATALOG'}
                        </span>
                    </div>
                )}

                <div className="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                    {totalStock > 0 ? (
                        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-zinc-950/85 px-2 py-0.5 font-mono text-[10px] font-medium text-emerald-400 backdrop-blur">
                            <span className="size-1.5 rounded-full bg-emerald-400" />
                            Stock: {activeVariant?.stock ?? totalStock}
                        </span>
                    ) : (
                        <span className="inline-flex items-center rounded-full border border-zinc-700 bg-zinc-950/85 px-2 py-0.5 font-mono text-[10px] text-zinc-400 backdrop-blur">
                            Agotado
                        </span>
                    )}
                </div>
            </Link>

            <div className="flex flex-1 flex-col p-4">
                <div className="flex items-start justify-between gap-2">
                    <Link
                        href={portalPaths.storefront.product(
                            routeCtx,
                            product.slug,
                        )}
                        className="line-clamp-2 text-sm font-semibold text-zinc-100 transition group-hover:text-primary"
                    >
                        {product.name}
                    </Link>
                </div>

                {metadataEntries.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1">
                        {metadataEntries.map(([k, v]) => (
                            <span
                                key={k}
                                className="rounded border border-zinc-800 bg-zinc-900/80 px-1.5 py-0.5 text-[10px] text-zinc-400"
                            >
                                {k}: {stringifyMetadataValue(v)}
                            </span>
                        ))}
                    </div>
                )}

                {product.matched_variants.length > 1 && (
                    <div className="mt-3 flex flex-wrap gap-1">
                        {product.matched_variants.slice(0, 4).map((variant) => {
                            const label =
                                Object.values(variant.metadata ?? {})
                                    .map((val) => stringifyMetadataValue(val))
                                    .join(' / ') || variant.sku;
                            const isSelected = activeVariant?.id === variant.id;

                            return (
                                <button
                                    key={variant.id}
                                    type="button"
                                    onClick={() =>
                                        setSelectedVariantId(variant.id)
                                    }
                                    className={`rounded border px-2 py-0.5 font-mono text-[10px] transition ${
                                        isSelected
                                            ? 'border-primary bg-primary/20 text-zinc-50'
                                            : variant.stock > 0
                                              ? 'border-zinc-800 bg-zinc-900 text-zinc-400 hover:border-zinc-700 hover:text-zinc-200'
                                              : 'border-zinc-800/60 bg-zinc-950 text-zinc-600 line-through'
                                    }`}
                                >
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                )}

                <div className="mt-auto flex items-center justify-between gap-2 border-t border-zinc-800/70 pt-3.5">
                    <div>
                        <p className="font-mono text-base font-bold text-zinc-50">
                            {formatMoney(displayPrice, currencyCode)}
                        </p>
                        {activeVariant && (
                            <p className="font-mono text-[10px] text-zinc-500">
                                {activeVariant.sku}
                            </p>
                        )}
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        disabled={!canQuickAdd}
                        onClick={handleQuickAdd}
                        className="h-8 gap-1.5 bg-primary px-3 text-xs font-medium text-primary-foreground hover:opacity-90 disabled:bg-zinc-800 disabled:text-zinc-500"
                    >
                        <ShoppingBag className="size-3.5" />
                        <span>{canQuickAdd ? 'Agregar' : 'Sin stock'}</span>
                    </Button>
                </div>
            </div>
        </article>
    );
}

function CatalogContent() {
    const { tenantSlug, tenantName, currencyCode, bootstrap, categories } =
        useTenant();

    const [searchDraft, setSearchDraft] = useState<string>('');
    const [debouncedQuery, setDebouncedQuery] = useState<string>('');
    const [sort, setSort] = useState<StorefrontProductSort>('newest');
    const [page, setPage] = useState<number>(1);
    const [filters, setFilters] =
        useState<ActiveCatalogFilters>(INITIAL_FILTERS);
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState<boolean>(false);

    const [products, setProducts] = useState<StorefrontProductCard[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setDebouncedQuery(searchDraft.trim());
            setPage(1);
        }, 280);
        return () => window.clearTimeout(timer);
    }, [searchDraft]);

    useEffect(() => {
        const controller = new AbortController();
        setIsLoading(true);
        setError(null);

        const query: StorefrontProductQuery = {
            page,
            per_page: 12,
            sort,
        };

        if (debouncedQuery) {
            query.q = debouncedQuery;
        }
        if (filters.categorySlug) {
            query.category_slug = filters.categorySlug;
        }
        if (filters.priceMin !== '' && Number(filters.priceMin) >= 0) {
            query.price_min = Number(filters.priceMin);
        }
        if (filters.priceMax !== '' && Number(filters.priceMax) >= 0) {
            query.price_max = Number(filters.priceMax);
        }
        if (filters.inStock) {
            query.in_stock = true;
        }
        if (Object.keys(filters.attr).length > 0) {
            query.attr = filters.attr;
        }

        void (async () => {
            try {
                const res = await commerceApi.storefront.getProducts(
                    tenantSlug,
                    query,
                    controller.signal,
                );
                setProducts(res.data);
                setMeta(res.meta);
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                setError(
                    err instanceof ApiClientError
                        ? err.message
                        : 'Error al cargar el catálogo.',
                );
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        })();

        return () => controller.abort();
    }, [tenantSlug, debouncedQuery, sort, page, filters]);

    const handleFilterChange = (next: ActiveCatalogFilters) => {
        setFilters(next);
        setPage(1);
    };

    const handleResetFilters = () => {
        setFilters(INITIAL_FILTERS);
        setSearchDraft('');
        setDebouncedQuery('');
        setPage(1);
    };

    const bannerUrl = bootstrap?.branding.banner_url;

    return (
        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <Head title={`Catálogo · ${tenantName}`} />

            {/* Hero / Search Header */}
            <section className="relative mb-8 overflow-hidden rounded-2xl border border-zinc-800 bg-[#121215] p-6 sm:p-8">
                {bannerUrl && (
                    <img
                        src={bannerUrl}
                        alt={tenantName}
                        className="pointer-events-none absolute inset-0 size-full object-cover opacity-15"
                    />
                )}
                <div className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-primary/15 blur-3xl" />

                <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-xl">
                        <div className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-300">
                            <Sparkles className="size-3" />
                            <span>
                                Compra sin crear cuenta · Confirmación directa
                                en WhatsApp
                            </span>
                        </div>
                        <h1 className="mt-3 text-2xl font-bold tracking-tight text-zinc-50 sm:text-3xl">
                            {tenantName}
                        </h1>
                        <p className="mt-1.5 text-xs text-zinc-400 sm:text-sm">
                            Inventario verificado en tiempo real, descuentos
                            automáticos y cotización instantánea por zona de
                            envío.
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-2.5 sm:flex-row lg:w-auto">
                        <div className="relative flex-1 sm:w-72">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500" />
                            <Input
                                value={searchDraft}
                                onChange={(e) => setSearchDraft(e.target.value)}
                                placeholder="Buscar producto, SKU o atributo..."
                                className="h-10 border-zinc-800 bg-[#09090b]/90 pr-8 pl-9 text-xs text-zinc-100 placeholder:text-zinc-500"
                            />
                            {searchDraft && (
                                <button
                                    type="button"
                                    onClick={() => setSearchDraft('')}
                                    className="absolute top-1/2 right-2.5 -translate-y-1/2 text-zinc-500 hover:text-zinc-200"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>

                        <div className="flex gap-2">
                            <select
                                value={sort}
                                onChange={(e) => {
                                    setSort(
                                        e.target.value as StorefrontProductSort,
                                    );
                                    setPage(1);
                                }}
                                aria-label="Ordenar productos"
                                className="h-10 flex-1 rounded-md border border-zinc-800 bg-[#09090b]/90 px-3 text-xs text-zinc-200 focus:border-primary focus:outline-none sm:w-44"
                            >
                                <option value="newest">Más recientes</option>
                                <option value="price_asc">
                                    Precio: menor a mayor
                                </option>
                                <option value="price_desc">
                                    Precio: mayor a menor
                                </option>
                            </select>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setMobileFiltersOpen(true)}
                                className="h-10 border-zinc-800 bg-[#09090b] text-xs text-zinc-200 lg:hidden"
                            >
                                <SlidersHorizontal className="mr-1.5 size-3.5 text-primary" />
                                Filtros
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            {/* Main Grid with Schema-Driven Faceted Sidebar */}
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-[250px_1fr]">
                <div className="hidden lg:block">
                    <FacetedFilterSidebar
                        categories={categories}
                        filters={bootstrap?.filters ?? []}
                        value={filters}
                        onChange={handleFilterChange}
                        onReset={handleResetFilters}
                        currencyCode={currencyCode}
                    />
                </div>

                <div>
                    <div className="mb-4 flex items-center justify-between text-xs text-zinc-400">
                        <span>
                            {meta ? (
                                <>
                                    Mostrando{' '}
                                    <strong className="font-mono text-zinc-200">
                                        {products.length}
                                    </strong>{' '}
                                    de{' '}
                                    <strong className="font-mono text-zinc-200">
                                        {meta.total}
                                    </strong>{' '}
                                    productos
                                </>
                            ) : (
                                'Cargando catálogo...'
                            )}
                        </span>
                    </div>

                    {error && (
                        <div className="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-xs text-rose-300">
                            {error}
                        </div>
                    )}

                    {isLoading ? (
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            {Array.from({ length: 6 }).map((_, idx) => (
                                <div
                                    key={idx}
                                    className="overflow-hidden rounded-xl border border-zinc-800 bg-[#121215] p-4"
                                >
                                    <Skeleton className="aspect-[4/3] w-full rounded-lg bg-zinc-800/70" />
                                    <Skeleton className="mt-4 h-4 w-3/4 bg-zinc-800/70" />
                                    <Skeleton className="mt-2 h-3 w-1/2 bg-zinc-800/70" />
                                    <div className="mt-5 flex justify-between">
                                        <Skeleton className="h-6 w-20 bg-zinc-800/70" />
                                        <Skeleton className="h-8 w-24 bg-zinc-800/70" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : products.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-800 bg-[#121215]/50 px-6 py-16 text-center">
                            <Package className="size-10 text-zinc-600" />
                            <h2 className="mt-3 text-base font-semibold text-zinc-200">
                                Sin coincidencias para estos filtros
                            </h2>
                            <p className="mt-1 max-w-sm text-xs text-zinc-400">
                                Prueba ajustando el rango de precios, quitando
                                alguna faceta dinámica o cambiando tu búsqueda.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleResetFilters}
                                className="mt-4 border-zinc-700 bg-zinc-900 text-xs text-zinc-200"
                            >
                                Restablecer catálogo
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                {products.map((product) => (
                                    <ProductCardItem
                                        key={product.id}
                                        product={product}
                                    />
                                ))}
                            </div>

                            {meta && meta.last_page > 1 && (
                                <div className="mt-8 flex items-center justify-between border-t border-zinc-800 pt-5">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page <= 1}
                                        onClick={() =>
                                            setPage((p) => Math.max(1, p - 1))
                                        }
                                        className="border-zinc-800 bg-[#121215] text-xs text-zinc-200"
                                    >
                                        <ChevronLeft className="mr-1 size-3.5" />
                                        Anterior
                                    </Button>

                                    <span className="font-mono text-xs text-zinc-400">
                                        Página {meta.current_page} de{' '}
                                        {meta.last_page}
                                    </span>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            meta.current_page >= meta.last_page
                                        }
                                        onClick={() =>
                                            setPage((p) =>
                                                Math.min(meta.last_page, p + 1),
                                            )
                                        }
                                        className="border-zinc-800 bg-[#121215] text-xs text-zinc-200"
                                    >
                                        Siguiente
                                        <ChevronRight className="ml-1 size-3.5" />
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Mobile Filter Drawer */}
            <Sheet open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}>
                <SheetContent
                    side="left"
                    className="w-80 overflow-y-auto border-r border-zinc-800 bg-[#0d0d10] p-5 text-zinc-100"
                >
                    <SheetHeader className="mb-4 p-0">
                        <SheetTitle className="text-sm font-semibold text-zinc-100">
                            Filtrar Catálogo
                        </SheetTitle>
                    </SheetHeader>
                    <FacetedFilterSidebar
                        categories={categories}
                        filters={bootstrap?.filters ?? []}
                        value={filters}
                        onChange={handleFilterChange}
                        onReset={handleResetFilters}
                        currencyCode={currencyCode}
                    />
                </SheetContent>
            </Sheet>
        </div>
    );
}

export default function StorefrontCatalogPage(props: PortalShellPageProps) {
    return (
        <StorefrontLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
        >
            <CatalogContent />
        </StorefrontLayout>
    );
}
