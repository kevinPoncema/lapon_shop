import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Loader2,
    Minus,
    Package,
    Plus,
    Sparkles,
    Tag,
    Trash2,
    Truck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCart } from '@/contexts/cart-context';
import { useTenant } from '@/contexts/tenant-context';
import {
    formatMoney,
    formatVariantAttributes,
    translateCouponRejection,
} from '@/lib/formatters';
import { portalPaths } from '@/lib/portal-paths';

export function CartQuoteDrawer() {
    const { currencyCode, routeCtx, bootstrap } = useTenant();
    const {
        items,
        totalQuantity,
        couponCode,
        shippingZoneCode,
        quote,
        isQuoting,
        quoteError,
        isDrawerOpen,
        setDrawerOpen,
        updateQuantity,
        removeItem,
        setCouponCode,
        setShippingZoneCode,
    } = useCart();

    const [couponDraft, setCouponDraft] = useState<string>(couponCode);

    const shippingZones = bootstrap?.shipping_zones ?? [];
    const quotedByVariant = new Map(
        (quote?.items ?? []).map((q) => [q.variant_id, q]),
    );

    const localSubtotal = items.reduce(
        (sum, item) => sum + item.unitPrice * item.quantity,
        0,
    );

    const handleApplyCoupon = (e: React.FormEvent) => {
        e.preventDefault();
        setCouponCode(couponDraft);
    };

    const handleClearCoupon = () => {
        setCouponDraft('');
        setCouponCode('');
    };

    const canCheckout =
        items.length > 0 && !isQuoting && (quote ? quote.is_valid : true);

    return (
        <Sheet open={isDrawerOpen} onOpenChange={setDrawerOpen}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-0 border-l border-zinc-800 bg-[#0d0d10] p-0 text-zinc-100 sm:max-w-md"
            >
                <SheetHeader className="border-b border-zinc-800/80 bg-[#121215] px-5 py-4">
                    <div className="flex items-center justify-between pr-6">
                        <div>
                            <SheetTitle className="flex items-center gap-2 text-base font-semibold tracking-tight text-zinc-50">
                                Bolsa de Compra
                                <span className="rounded-full border border-zinc-700 bg-zinc-800/90 px-2 py-0.5 font-mono text-xs text-zinc-300">
                                    {totalQuantity}
                                </span>
                            </SheetTitle>
                            <SheetDescription className="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-400">
                                {isQuoting ? (
                                    <>
                                        <Loader2 className="size-3 animate-spin text-primary" />
                                        <span>
                                            Recalculando precios y stock en
                                            servidor...
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <span className="size-1.5 rounded-full bg-emerald-400" />
                                        <span>
                                            Cotización verificada en tiempo real
                                        </span>
                                    </>
                                )}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                {items.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center">
                        <div className="mb-4 flex size-14 items-center justify-center rounded-2xl border border-zinc-800 bg-zinc-900/70 text-zinc-500">
                            <Package className="size-6" />
                        </div>
                        <p className="text-sm font-medium text-zinc-200">
                            Tu bolsa está vacía
                        </p>
                        <p className="mt-1 max-w-xs text-xs text-zinc-400">
                            Explora el catálogo y agrega variantes para obtener
                            una cotización automática con promociones y envío.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            className="mt-5 border-zinc-700 bg-zinc-900 text-zinc-200 hover:bg-zinc-800"
                            onClick={() => setDrawerOpen(false)}
                        >
                            Seguir explorando
                        </Button>
                    </div>
                ) : (
                    <>
                        <div className="flex-1 divide-y divide-zinc-800/70 overflow-y-auto px-5">
                            {items.map((item) => {
                                const serverItem = quotedByVariant.get(
                                    item.variantId,
                                );
                                const effectiveUnitPrice =
                                    serverItem?.unit_price ?? item.unitPrice;
                                const effectiveLineTotal =
                                    serverItem?.line_total ??
                                    item.unitPrice * item.quantity;
                                const hasStockIssue =
                                    serverItem !== undefined &&
                                    !serverItem.has_sufficient_stock;
                                const attrsLabel =
                                    item.variantLabel ||
                                    formatVariantAttributes(
                                        serverItem?.variant_attributes,
                                    );

                                return (
                                    <div
                                        key={item.variantId}
                                        className="flex gap-3.5 py-4"
                                    >
                                        <div className="relative size-16 shrink-0 overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900">
                                            {item.image ? (
                                                <img
                                                    src={item.image}
                                                    alt={item.productName}
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center text-zinc-600">
                                                    <Package className="size-5" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-2">
                                                <Link
                                                    href={portalPaths.storefront.product(
                                                        routeCtx,
                                                        item.productSlug,
                                                    )}
                                                    onClick={() =>
                                                        setDrawerOpen(false)
                                                    }
                                                    className="truncate text-sm font-medium text-zinc-100 hover:text-primary"
                                                >
                                                    {serverItem?.product_name ??
                                                        item.productName}
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeItem(
                                                            item.variantId,
                                                        )
                                                    }
                                                    className="text-zinc-500 transition hover:text-rose-400"
                                                    aria-label="Eliminar artículo"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </div>

                                            <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-400">
                                                <span className="font-mono text-zinc-500">
                                                    {serverItem?.sku ??
                                                        item.sku}
                                                </span>
                                                {attrsLabel && (
                                                    <>
                                                        <span>·</span>
                                                        <span>
                                                            {attrsLabel}
                                                        </span>
                                                    </>
                                                )}
                                            </div>

                                            {hasStockIssue && (
                                                <div className="mt-1.5 flex items-center gap-1.5 rounded border border-rose-500/30 bg-rose-500/10 px-2 py-1 text-[11px] text-rose-300">
                                                    <AlertTriangle className="size-3 shrink-0" />
                                                    <span>
                                                        Stock disponible:{' '}
                                                        {
                                                            serverItem.available_stock
                                                        }{' '}
                                                        uds. Ajusta la cantidad.
                                                    </span>
                                                </div>
                                            )}

                                            <div className="mt-2.5 flex items-center justify-between">
                                                <div className="inline-flex items-center rounded-md border border-zinc-800 bg-zinc-900/90">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item.variantId,
                                                                item.quantity -
                                                                    1,
                                                            )
                                                        }
                                                        className="px-2 py-1 text-zinc-400 hover:text-zinc-100"
                                                        aria-label="Disminuir cantidad"
                                                    >
                                                        <Minus className="size-3" />
                                                    </button>
                                                    <span className="min-w-7 text-center font-mono text-xs text-zinc-100">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            updateQuantity(
                                                                item.variantId,
                                                                item.quantity +
                                                                    1,
                                                            )
                                                        }
                                                        className="px-2 py-1 text-zinc-400 hover:text-zinc-100"
                                                        aria-label="Aumentar cantidad"
                                                    >
                                                        <Plus className="size-3" />
                                                    </button>
                                                </div>

                                                <div className="text-right">
                                                    <p className="font-mono text-sm font-semibold text-zinc-100">
                                                        {formatMoney(
                                                            effectiveLineTotal,
                                                            currencyCode,
                                                        )}
                                                    </p>
                                                    {item.quantity > 1 && (
                                                        <p className="font-mono text-[10px] text-zinc-500">
                                                            {formatMoney(
                                                                effectiveUnitPrice,
                                                                currencyCode,
                                                            )}{' '}
                                                            c/u
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="space-y-3.5 border-t border-zinc-800 bg-[#121215] p-5">
                            {/* Cupón / Código de Afiliado */}
                            <form
                                onSubmit={handleApplyCoupon}
                                className="space-y-1.5"
                            >
                                <label className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">
                                    <Tag className="size-3 text-primary" />
                                    Cupón o Código de Referido
                                </label>
                                <div className="flex gap-2">
                                    <Input
                                        value={couponDraft}
                                        onChange={(e) =>
                                            setCouponDraft(
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="Ej. KINETIC10"
                                        className="h-8 border-zinc-800 bg-zinc-950 font-mono text-xs text-zinc-100 uppercase placeholder:text-zinc-600"
                                    />
                                    {couponCode ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleClearCoupon}
                                            className="h-8 border-zinc-700 bg-zinc-900 px-2.5 text-xs text-zinc-300"
                                        >
                                            <X className="mr-1 size-3" />
                                            Quitar
                                        </Button>
                                    ) : (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            variant="secondary"
                                            disabled={
                                                couponDraft.trim().length === 0
                                            }
                                            className="h-8 border border-zinc-700 bg-zinc-800 px-3 text-xs text-zinc-100 hover:bg-zinc-700"
                                        >
                                            Aplicar
                                        </Button>
                                    )}
                                </div>

                                {quote?.summary.coupon.applied && (
                                    <p className="flex items-center gap-1.5 text-xs text-emerald-400">
                                        <CheckCircle2 className="size-3.5 shrink-0" />
                                        <span>
                                            Cupón{' '}
                                            <strong className="font-mono">
                                                {quote.summary.coupon.code}
                                            </strong>{' '}
                                            aplicado (-
                                            {formatMoney(
                                                quote.summary.coupon
                                                    .discount_amount,
                                                currencyCode,
                                            )}
                                            )
                                        </span>
                                    </p>
                                )}

                                {couponCode &&
                                    quote &&
                                    !quote.summary.coupon.applied && (
                                        <p className="flex items-center gap-1.5 text-xs text-amber-300">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            <span>
                                                {translateCouponRejection(
                                                    quote.summary.coupon
                                                        .rejection_code,
                                                    quote.summary.coupon
                                                        .message,
                                                )}
                                            </span>
                                        </p>
                                    )}
                            </form>

                            {/* Zona de Envío */}
                            {shippingZones.length > 0 && (
                                <div className="space-y-1.5">
                                    <label className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-zinc-400 uppercase">
                                        <Truck className="size-3 text-primary" />
                                        Zona de Entrega Estimada
                                    </label>
                                    <select
                                        value={shippingZoneCode}
                                        onChange={(e) =>
                                            setShippingZoneCode(e.target.value)
                                        }
                                        className="h-8 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100 focus:border-primary focus:outline-none"
                                    >
                                        {shippingZones.map((zone) => (
                                            <option
                                                key={zone.code}
                                                value={zone.code}
                                            >
                                                {zone.name} (
                                                {zone.fee === 0
                                                    ? 'Gratis'
                                                    : formatMoney(
                                                          zone.fee,
                                                          currencyCode,
                                                      )}
                                                )
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {quoteError && (
                                <div className="rounded-md border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs text-rose-300">
                                    {quoteError}
                                </div>
                            )}

                            {/* Resumen Financiero del Servidor */}
                            <div className="space-y-1.5 rounded-lg border border-zinc-800/90 bg-zinc-950/70 p-3 text-xs">
                                <div className="flex justify-between text-zinc-400">
                                    <span>Subtotal</span>
                                    <span className="font-mono text-zinc-200">
                                        {formatMoney(
                                            quote?.summary.subtotal ??
                                                localSubtotal,
                                            currencyCode,
                                        )}
                                    </span>
                                </div>

                                {quote?.summary.auto_promotion.applied && (
                                    <div className="flex justify-between text-emerald-400">
                                        <span className="flex items-center gap-1">
                                            <Sparkles className="size-3" />
                                            {quote.summary.auto_promotion
                                                .name ?? 'Promo automática'}
                                        </span>
                                        <span className="font-mono">
                                            -
                                            {formatMoney(
                                                quote.summary.auto_promotion
                                                    .discount_amount,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                )}

                                {quote?.summary.coupon.applied && (
                                    <div className="flex justify-between text-emerald-400">
                                        <span>
                                            Cupón ({quote.summary.coupon.code})
                                        </span>
                                        <span className="font-mono">
                                            -
                                            {formatMoney(
                                                quote.summary.coupon
                                                    .discount_amount,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                )}

                                {quote && (
                                    <div className="flex justify-between text-zinc-400">
                                        <span>
                                            Envío (
                                            {quote.summary.shipping.zone_name ??
                                                'Estándar'}
                                            )
                                        </span>
                                        <span className="font-mono text-zinc-200">
                                            {quote.summary.shipping.is_free ? (
                                                <span className="font-semibold text-emerald-400">
                                                    GRATIS
                                                </span>
                                            ) : (
                                                formatMoney(
                                                    quote.summary.shipping
                                                        .shipping_fee,
                                                    currencyCode,
                                                )
                                            )}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-baseline justify-between border-t border-zinc-800 pt-2">
                                    <span className="text-xs font-semibold text-zinc-100">
                                        Total Neto
                                    </span>
                                    <span className="font-mono text-base font-bold text-zinc-50">
                                        {formatMoney(
                                            quote?.summary.total_net ??
                                                localSubtotal,
                                            currencyCode,
                                        )}
                                    </span>
                                </div>
                            </div>

                            {canCheckout ? (
                                <Button
                                    asChild
                                    className="h-10 w-full bg-emerald-500 font-semibold text-zinc-950 hover:bg-emerald-400"
                                >
                                    <Link
                                        href={portalPaths.storefront.checkout(
                                            routeCtx,
                                        )}
                                        onClick={() => setDrawerOpen(false)}
                                    >
                                        <span>
                                            Continuar a Checkout WhatsApp
                                        </span>
                                        <ArrowRight className="ml-1.5 size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    disabled
                                    className="h-10 w-full bg-zinc-800 text-zinc-500"
                                >
                                    {isQuoting
                                        ? 'Verificando disponibilidad...'
                                        : 'Revisa el stock disponible'}
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
