import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    MessageCircle,
    Minus,
    Package,
    Plus,
    ShoppingBag,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
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
    type FormattedProductDetail,
    type FormattedVariantDetail,
    type PortalShellPageProps,
} from '@/types/commerce';

interface ProductDetailPageProps extends PortalShellPageProps {
    productSlug: string;
}

function ProductDetailContent({ productSlug }: { productSlug: string }) {
    const { tenantSlug, tenantName, currencyCode, routeCtx } = useTenant();
    const { addItem, setDrawerOpen } = useCart();

    const [product, setProduct] = useState<FormattedProductDetail | null>(null);
    const [selectedVariantId, setSelectedVariantId] = useState<string>('');
    const [selectedImageIdx, setSelectedImageIdx] = useState<number>(0);
    const [quantity, setQuantity] = useState<number>(1);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setIsLoading(true);
        setError(null);

        void (async () => {
            try {
                const res = await commerceApi.storefront.getProduct(
                    tenantSlug,
                    productSlug,
                    controller.signal,
                );
                setProduct(res.data);

                const preferred =
                    res.data.variants.find(
                        (v) => v.is_default && v.stock > 0,
                    ) ??
                    res.data.variants.find((v) => v.stock > 0) ??
                    res.data.variants[0];

                if (preferred) {
                    setSelectedVariantId(preferred.id);
                }
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                setError(
                    err instanceof ApiClientError
                        ? err.message
                        : 'No se pudo cargar el producto solicitado.',
                );
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        })();

        return () => controller.abort();
    }, [tenantSlug, productSlug]);

    if (isLoading) {
        return (
            <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <Skeleton className="aspect-square w-full rounded-2xl bg-zinc-900" />
                    <div className="space-y-4">
                        <Skeleton className="h-8 w-3/4 bg-zinc-900" />
                        <Skeleton className="h-6 w-1/3 bg-zinc-900" />
                        <Skeleton className="h-24 w-full bg-zinc-900" />
                        <Skeleton className="h-12 w-full bg-zinc-900" />
                    </div>
                </div>
            </div>
        );
    }

    if (error || !product) {
        return (
            <div className="mx-auto max-w-3xl px-4 py-16 text-center">
                <Package className="mx-auto size-12 text-zinc-600" />
                <h1 className="mt-4 text-lg font-semibold text-zinc-100">
                    {error ?? 'Producto no disponible'}
                </h1>
                <Button
                    asChild
                    variant="outline"
                    className="mt-6 border-zinc-800 bg-[#121215]"
                >
                    <Link href={portalPaths.storefront.catalog(routeCtx)}>
                        <ArrowLeft className="mr-2 size-4" />
                        Volver al catálogo
                    </Link>
                </Button>
            </div>
        );
    }

    const activeVariant: FormattedVariantDetail | undefined =
        product.variants.find((v) => v.id === selectedVariantId) ??
        product.variants[0];

    const unitPrice = activeVariant?.price ?? product.base_price;
    const maxStock = activeVariant?.stock ?? 0;
    const inStock = maxStock > 0;
    const activeImage = product.images[selectedImageIdx] ?? product.images[0];
    const productMetadataEntries = Object.entries(product.metadata ?? {})
        .map(([key, val]) => [key, stringifyMetadataValue(val).trim()] as const)
        .filter(([, text]) => text !== '');

    const handleAddToCart = () => {
        if (!activeVariant || !inStock) {
            return;
        }
        addItem(
            {
                variantId: activeVariant.id,
                productName: product.name,
                productSlug: product.slug,
                sku: activeVariant.sku,
                unitPrice: activeVariant.price,
                image: product.images[0] ?? null,
                variantLabel: formatVariantAttributes(activeVariant.metadata),
            },
            Math.min(quantity, maxStock),
        );
    };

    const handleInstantWhatsAppCheckout = () => {
        if (!activeVariant || !inStock) {
            return;
        }
        addItem(
            {
                variantId: activeVariant.id,
                productName: product.name,
                productSlug: product.slug,
                sku: activeVariant.sku,
                unitPrice: activeVariant.price,
                image: product.images[0] ?? null,
                variantLabel: formatVariantAttributes(activeVariant.metadata),
            },
            Math.min(quantity, maxStock),
        );
        setDrawerOpen(false);
        router.visit(portalPaths.storefront.checkout(routeCtx));
    };

    return (
        <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <Head title={`${product.name} · ${tenantName}`} />

            <div className="mb-6">
                <Link
                    href={portalPaths.storefront.catalog(routeCtx)}
                    className="inline-flex items-center gap-1.5 text-xs text-zinc-400 transition hover:text-zinc-100"
                >
                    <ArrowLeft className="size-3.5" />
                    <span>Volver al catálogo</span>
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
                {/* Galería */}
                <div className="space-y-3 lg:col-span-6">
                    <div className="aspect-square overflow-hidden rounded-2xl border border-zinc-800 bg-[#121215]">
                        {activeImage ? (
                            <img
                                src={activeImage}
                                alt={product.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="flex size-full flex-col items-center justify-center gap-2 text-zinc-600">
                                <Package className="size-12" />
                                <span className="font-mono text-xs">
                                    {activeVariant?.sku}
                                </span>
                            </div>
                        )}
                    </div>

                    {product.images.length > 1 && (
                        <div className="flex gap-2.5 overflow-x-auto pb-1">
                            {product.images.map((imgUrl, idx) => (
                                <button
                                    key={imgUrl}
                                    type="button"
                                    onClick={() => setSelectedImageIdx(idx)}
                                    className={`size-16 shrink-0 overflow-hidden rounded-lg border transition ${
                                        idx === selectedImageIdx
                                            ? 'border-primary ring-2 ring-primary/30'
                                            : 'border-zinc-800 opacity-70 hover:opacity-100'
                                    }`}
                                >
                                    <img
                                        src={imgUrl}
                                        alt={`${product.name} ${idx + 1}`}
                                        className="size-full object-cover"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Panel de Configuración y Compra */}
                <div className="flex flex-col lg:col-span-6">
                    <div className="flex items-center gap-2">
                        {inStock ? (
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 font-mono text-xs text-emerald-300">
                                <CheckCircle2 className="size-3.5" />
                                Disponible ({maxStock} en inventario)
                            </span>
                        ) : (
                            <span className="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-0.5 font-mono text-xs text-rose-300">
                                Variante agotada
                            </span>
                        )}

                        {activeVariant && (
                            <span className="font-mono text-xs text-zinc-500">
                                SKU: {activeVariant.sku}
                            </span>
                        )}
                    </div>

                    <h1 className="mt-3 text-2xl font-bold tracking-tight text-zinc-50 sm:text-3xl">
                        {product.name}
                    </h1>

                    <div className="mt-3 flex items-baseline gap-3">
                        <span className="font-mono text-3xl font-bold text-zinc-50">
                            {formatMoney(unitPrice, currencyCode)}
                        </span>
                        {activeVariant?.price_override !== null &&
                            activeVariant?.price_override !== undefined &&
                            activeVariant.price !== product.base_price && (
                                <span className="font-mono text-xs text-zinc-500">
                                    Base:{' '}
                                    {formatMoney(
                                        product.base_price,
                                        currencyCode,
                                    )}
                                </span>
                            )}
                    </div>

                    {product.description && (
                        <p className="mt-4 text-sm leading-relaxed text-zinc-300">
                            {product.description}
                        </p>
                    )}

                    {/* Selector de Variantes */}
                    {product.variants.length > 0 && (
                        <div className="mt-6 space-y-2.5">
                            <label className="text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                                Seleccionar Variante ({product.variants.length})
                            </label>
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {product.variants.map((variant) => {
                                    const isSelected =
                                        activeVariant?.id === variant.id;
                                    const label =
                                        formatVariantAttributes(
                                            variant.metadata,
                                        ) || variant.sku;

                                    return (
                                        <button
                                            key={variant.id}
                                            type="button"
                                            onClick={() => {
                                                setSelectedVariantId(
                                                    variant.id,
                                                );
                                                setQuantity(1);
                                            }}
                                            className={`flex items-center justify-between rounded-xl border p-3 text-left transition ${
                                                isSelected
                                                    ? 'border-primary bg-primary/15 ring-1 ring-primary/40'
                                                    : variant.stock > 0
                                                      ? 'border-zinc-800 bg-[#121215] hover:border-zinc-700'
                                                      : 'border-zinc-800/50 bg-zinc-950/60 opacity-60'
                                            }`}
                                        >
                                            <div className="min-w-0 pr-2">
                                                <p className="truncate text-xs font-medium text-zinc-100">
                                                    {label}
                                                </p>
                                                <p className="font-mono text-[11px] text-zinc-500">
                                                    {variant.stock > 0
                                                        ? `${variant.stock} disp.`
                                                        : 'Agotado'}
                                                </p>
                                            </div>
                                            <span className="font-mono text-xs font-semibold text-zinc-200">
                                                {formatMoney(
                                                    variant.price,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Cantidad + Botones */}
                    <div className="mt-6 space-y-3 border-t border-zinc-800 pt-6">
                        <div className="flex items-center gap-3">
                            <div className="inline-flex h-11 items-center rounded-xl border border-zinc-800 bg-[#121215]">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setQuantity((q) => Math.max(1, q - 1))
                                    }
                                    disabled={!inStock}
                                    className="px-3.5 text-zinc-400 hover:text-zinc-100 disabled:opacity-40"
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="min-w-10 text-center font-mono text-sm font-semibold text-zinc-100">
                                    {quantity}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setQuantity((q) =>
                                            Math.min(maxStock, q + 1),
                                        )
                                    }
                                    disabled={!inStock || quantity >= maxStock}
                                    className="px-3.5 text-zinc-400 hover:text-zinc-100 disabled:opacity-40"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>

                            <Button
                                type="button"
                                disabled={!inStock}
                                onClick={handleAddToCart}
                                className="h-11 flex-1 gap-2 bg-primary font-semibold text-primary-foreground hover:opacity-90"
                            >
                                <ShoppingBag className="size-4" />
                                <span>Agregar a la Bolsa</span>
                            </Button>
                        </div>

                        <Button
                            type="button"
                            disabled={!inStock}
                            onClick={handleInstantWhatsAppCheckout}
                            className="h-11 w-full gap-2 bg-emerald-500 font-semibold text-zinc-950 hover:bg-emerald-400"
                        >
                            <MessageCircle className="size-4" />
                            <span>Comprar directo por WhatsApp</span>
                        </Button>
                    </div>

                    {/* Ficha Técnica Dinámica (metadata_schema) */}
                    {productMetadataEntries.length > 0 && (
                        <div className="mt-8 rounded-xl border border-zinc-800 bg-[#121215] p-4">
                            <h2 className="mb-3 text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                                Especificaciones del Producto
                            </h2>
                            <dl className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                {productMetadataEntries.map(([key, text]) => (
                                    <div
                                        key={key}
                                        className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 px-3 py-2"
                                    >
                                        <dt className="text-[11px] text-zinc-500 capitalize">
                                            {key.replace(/_/g, ' ')}
                                        </dt>
                                        <dd className="mt-0.5 text-xs font-medium text-zinc-200">
                                            {text}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function StorefrontProductDetailPage(
    props: ProductDetailPageProps,
) {
    return (
        <StorefrontLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
        >
            <ProductDetailContent productSlug={props.productSlug} />
        </StorefrontLayout>
    );
}
