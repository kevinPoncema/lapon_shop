import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Copy,
    ExternalLink,
    Loader2,
    Lock,
    MapPin,
    MessageCircle,
    Package,
    Sparkles,
    Tag,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCart } from '@/contexts/cart-context';
import { useTenant } from '@/contexts/tenant-context';
import { StorefrontLayout } from '@/layouts/storefront-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import { formatMoney, translateCouponRejection } from '@/lib/formatters';
import { portalPaths } from '@/lib/portal-paths';
import {
    type PortalShellPageProps,
    type StorefrontCheckoutResponse,
} from '@/types/commerce';

function CheckoutContent() {
    const { tenantSlug, tenantName, currencyCode, routeCtx, bootstrap } =
        useTenant();
    const {
        items,
        couponCode,
        shippingZoneCode,
        quote,
        isQuoting,
        setCouponCode,
        setShippingZoneCode,
        clearCart,
        getCheckoutIdempotencyKey,
        resetCheckoutIdempotencyKey,
    } = useCart();

    const [fullName, setFullName] = useState<string>('');
    const [whatsappPhone, setWhatsappPhone] = useState<string>('');
    const [email, setEmail] = useState<string>('');
    const [addressLine, setAddressLine] = useState<string>('');
    const [reference, setReference] = useState<string>('');
    const [couponInput, setCouponInput] = useState<string>(couponCode);

    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>(
        {},
    );
    const [completedOrder, setCompletedOrder] =
        useState<StorefrontCheckoutResponse | null>(null);

    const shippingZones = bootstrap?.shipping_zones ?? [];
    const effectiveRegionCode =
        shippingZoneCode || shippingZones[0]?.code || 'GENERAL';

    const localSubtotal = items.reduce(
        (acc, item) => acc + item.unitPrice * item.quantity,
        0,
    );

    const handleSubmitOrder = async (e: React.FormEvent) => {
        e.preventDefault();
        if (items.length === 0 || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        setSubmitError(null);
        setFieldErrors({});

        const idempotencyKey = getCheckoutIdempotencyKey();

        try {
            const result = await commerceApi.storefront.checkout(
                tenantSlug,
                {
                    customer_info: {
                        full_name: fullName.trim(),
                        whatsapp_phone: whatsappPhone.trim(),
                        email: email.trim(),
                    },
                    shipping_address: {
                        region_code: effectiveRegionCode,
                        address_line: addressLine.trim(),
                        reference: reference.trim() || null,
                    },
                    items: items.map((item) => ({
                        variant_id: item.variantId,
                        quantity: item.quantity,
                    })),
                    coupon_code: couponCode.trim() ? couponCode.trim() : null,
                },
                idempotencyKey,
            );

            setCompletedOrder(result);
            clearCart();
            resetCheckoutIdempotencyKey();
            toast.success(`Orden ${result.order_number} reservada con éxito.`);

            if (typeof window !== 'undefined' && result.whatsapp_redirect_url) {
                window.open(
                    result.whatsapp_redirect_url,
                    '_blank',
                    'noopener,noreferrer',
                );
            }
        } catch (err) {
            if (err instanceof ApiClientError) {
                setFieldErrors(err.validationErrors);
                setSubmitError(err.firstFieldError() ?? err.message);
            } else {
                setSubmitError(
                    'Ocurrió un problema de red al confirmar tu pedido. Puedes intentar nuevamente sin riesgo de orden duplicada.',
                );
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleCopyWhatsAppLink = async () => {
        if (!completedOrder?.whatsapp_redirect_url) {
            return;
        }
        try {
            await navigator.clipboard.writeText(
                completedOrder.whatsapp_redirect_url,
            );
            toast.success('Enlace de WhatsApp copiado al portapapeles.');
        } catch {
            toast.error('No se pudo copiar el enlace.');
        }
    };

    // Estado Post-Orden Exitosa
    if (completedOrder) {
        return (
            <div className="mx-auto max-w-xl px-4 py-12 sm:px-6">
                <Head
                    title={`Orden ${completedOrder.order_number} · ${tenantName}`}
                />

                <div className="overflow-hidden rounded-2xl border border-emerald-500/30 bg-[#121215] p-6 sm:p-8">
                    <div className="flex size-12 items-center justify-center rounded-2xl border border-emerald-500/40 bg-emerald-500/15 text-emerald-400">
                        <CheckCircle2 className="size-6" />
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <span className="rounded-md border border-zinc-700 bg-zinc-900 px-2.5 py-0.5 font-mono text-xs font-semibold text-zinc-100">
                            {completedOrder.order_number}
                        </span>
                        <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[11px] text-amber-300">
                            Stock reservado · Pendiente confirmación en WhatsApp
                        </span>
                    </div>

                    <h1 className="mt-3 text-2xl font-bold text-zinc-50">
                        ¡Tu pedido está listo para enviar!
                    </h1>
                    <p className="mt-1.5 text-xs leading-relaxed text-zinc-400 sm:text-sm">
                        Hemos apartado tus artículos y generado el resumen
                        exacto para{' '}
                        <strong className="text-zinc-200">{tenantName}</strong>.
                        Envía el mensaje prellenado en WhatsApp para coordinar
                        el pago y despacho.
                    </p>

                    <div className="mt-6 space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-4 text-xs">
                        <div className="flex justify-between text-zinc-400">
                            <span>Subtotal</span>
                            <span className="font-mono text-zinc-200">
                                {formatMoney(
                                    completedOrder.summary.subtotal,
                                    currencyCode,
                                )}
                            </span>
                        </div>
                        {completedOrder.summary.auto_discount_total > 0 && (
                            <div className="flex justify-between text-emerald-400">
                                <span>Descuento automático</span>
                                <span className="font-mono">
                                    -
                                    {formatMoney(
                                        completedOrder.summary
                                            .auto_discount_total,
                                        currencyCode,
                                    )}
                                </span>
                            </div>
                        )}
                        {completedOrder.summary.coupon_discount_total > 0 && (
                            <div className="flex justify-between text-emerald-400">
                                <span>Cupón aplicado</span>
                                <span className="font-mono">
                                    -
                                    {formatMoney(
                                        completedOrder.summary
                                            .coupon_discount_total,
                                        currencyCode,
                                    )}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between text-zinc-400">
                            <span>Envío</span>
                            <span className="font-mono text-zinc-200">
                                {completedOrder.summary.shipping_fee === 0
                                    ? 'GRATIS'
                                    : formatMoney(
                                          completedOrder.summary.shipping_fee,
                                          currencyCode,
                                      )}
                            </span>
                        </div>
                        <div className="flex items-baseline justify-between border-t border-zinc-800 pt-2.5">
                            <span className="font-semibold text-zinc-100">
                                Total Neto a Pagar
                            </span>
                            <span className="font-mono text-lg font-bold text-emerald-400">
                                {formatMoney(
                                    completedOrder.summary.total_net,
                                    currencyCode,
                                )}
                            </span>
                        </div>
                    </div>

                    <div className="mt-6 space-y-2.5">
                        <Button
                            asChild
                            className="h-12 w-full gap-2 bg-emerald-500 text-sm font-bold text-zinc-950 hover:bg-emerald-400"
                        >
                            <a
                                href={completedOrder.whatsapp_redirect_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle className="size-4" />
                                <span>Abrir y Enviar Pedido en WhatsApp</span>
                                <ExternalLink className="size-3.5" />
                            </a>
                        </Button>

                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void handleCopyWhatsAppLink()}
                                className="h-9 flex-1 border-zinc-800 bg-zinc-900 text-xs text-zinc-300"
                            >
                                <Copy className="mr-1.5 size-3.5" />
                                Copiar enlace WhatsApp
                            </Button>

                            <Button
                                asChild
                                variant="outline"
                                className="h-9 flex-1 border-zinc-800 bg-zinc-900 text-xs text-zinc-300"
                            >
                                <Link
                                    href={portalPaths.storefront.catalog(
                                        routeCtx,
                                    )}
                                >
                                    Volver a la tienda
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (items.length === 0) {
        return (
            <div className="mx-auto max-w-lg px-4 py-16 text-center">
                <Head title={`Checkout · ${tenantName}`} />
                <Package className="mx-auto size-12 text-zinc-600" />
                <h1 className="mt-4 text-lg font-semibold text-zinc-100">
                    No tienes artículos en tu bolsa
                </h1>
                <p className="mt-1 text-xs text-zinc-400">
                    Agrega productos desde la vitrina antes de iniciar el
                    checkout asistido.
                </p>
                <Button asChild className="mt-6 bg-primary">
                    <Link href={portalPaths.storefront.catalog(routeCtx)}>
                        Explorar Catálogo
                    </Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <Head title={`Checkout WhatsApp · ${tenantName}`} />

            <div className="mb-6 flex items-center justify-between">
                <Link
                    href={portalPaths.storefront.catalog(routeCtx)}
                    className="inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-zinc-100"
                >
                    <ArrowLeft className="size-3.5" />
                    <span>Seguir comprando</span>
                </Link>

                <span className="inline-flex items-center gap-1.5 font-mono text-[11px] text-zinc-400">
                    <Lock className="size-3 text-emerald-400" />
                    Checkout Idempotente Protegido
                </span>
            </div>

            <form
                onSubmit={(e) => void handleSubmitOrder(e)}
                className="grid grid-cols-1 gap-8 lg:grid-cols-12"
            >
                {/* Datos del Comprador y Entrega */}
                <div className="space-y-6 lg:col-span-7">
                    <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-5 sm:p-6">
                        <div className="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <User className="size-4 text-primary" />
                            <h2 className="text-sm font-semibold text-zinc-100">
                                1. Datos de Contacto (Sin crear cuenta)
                            </h2>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label
                                    htmlFor="full_name"
                                    className="text-xs text-zinc-300"
                                >
                                    Nombre Completo *
                                </Label>
                                <Input
                                    id="full_name"
                                    required
                                    maxLength={150}
                                    value={fullName}
                                    onChange={(e) =>
                                        setFullName(e.target.value)
                                    }
                                    placeholder="Ej. Andrea Morales"
                                    className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                                />
                                {fieldErrors[
                                    'customer_info.full_name'
                                ]?.[0] && (
                                    <p className="mt-1 text-xs text-rose-400">
                                        {
                                            fieldErrors[
                                                'customer_info.full_name'
                                            ][0]
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label
                                    htmlFor="whatsapp_phone"
                                    className="text-xs text-zinc-300"
                                >
                                    Número de WhatsApp *
                                </Label>
                                <Input
                                    id="whatsapp_phone"
                                    required
                                    maxLength={30}
                                    value={whatsappPhone}
                                    onChange={(e) =>
                                        setWhatsappPhone(e.target.value)
                                    }
                                    placeholder="+504 9999-9999"
                                    className="mt-1.5 border-zinc-800 bg-zinc-950 font-mono text-sm text-zinc-100"
                                />
                                {fieldErrors[
                                    'customer_info.whatsapp_phone'
                                ]?.[0] && (
                                    <p className="mt-1 text-xs text-rose-400">
                                        {
                                            fieldErrors[
                                                'customer_info.whatsapp_phone'
                                            ][0]
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label
                                    htmlFor="email"
                                    className="text-xs text-zinc-300"
                                >
                                    Correo Electrónico (vouchers y recibo) *
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    maxLength={150}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="andrea@correo.com"
                                    className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                                />
                                {fieldErrors['customer_info.email']?.[0] && (
                                    <p className="mt-1 text-xs text-rose-400">
                                        {fieldErrors['customer_info.email'][0]}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-5 sm:p-6">
                        <div className="mb-4 flex items-center gap-2 border-b border-zinc-800 pb-3">
                            <MapPin className="size-4 text-primary" />
                            <h2 className="text-sm font-semibold text-zinc-100">
                                2. Zona y Dirección de Entrega
                            </h2>
                        </div>

                        <div className="space-y-4">
                            {shippingZones.length > 0 ? (
                                <div>
                                    <Label className="text-xs text-zinc-300">
                                        Zona de Cobertura *
                                    </Label>
                                    <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        {shippingZones.map((zone) => {
                                            const active =
                                                effectiveRegionCode ===
                                                zone.code;
                                            return (
                                                <button
                                                    key={zone.code}
                                                    type="button"
                                                    onClick={() =>
                                                        setShippingZoneCode(
                                                            zone.code,
                                                        )
                                                    }
                                                    className={`flex items-center justify-between rounded-xl border p-3 text-left transition ${
                                                        active
                                                            ? 'border-primary bg-primary/15 ring-1 ring-primary/40'
                                                            : 'border-zinc-800 bg-zinc-950 hover:border-zinc-700'
                                                    }`}
                                                >
                                                    <div>
                                                        <p className="text-xs font-medium text-zinc-100">
                                                            {zone.name}
                                                        </p>
                                                        <p className="font-mono text-[10px] text-zinc-500">
                                                            {zone.code}
                                                        </p>
                                                    </div>
                                                    <span className="font-mono text-xs font-semibold text-zinc-200">
                                                        {zone.fee === 0
                                                            ? 'Gratis'
                                                            : formatMoney(
                                                                  zone.fee,
                                                                  currencyCode,
                                                              )}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ) : (
                                <div>
                                    <Label
                                        htmlFor="region_code"
                                        className="text-xs text-zinc-300"
                                    >
                                        Código de Región / Ciudad *
                                    </Label>
                                    <Input
                                        id="region_code"
                                        required
                                        value={effectiveRegionCode}
                                        onChange={(e) =>
                                            setShippingZoneCode(e.target.value)
                                        }
                                        className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                                    />
                                </div>
                            )}

                            <div>
                                <Label
                                    htmlFor="address_line"
                                    className="text-xs text-zinc-300"
                                >
                                    Dirección Exacta o Sucursal de Retiro *
                                </Label>
                                <Input
                                    id="address_line"
                                    required
                                    maxLength={255}
                                    value={addressLine}
                                    onChange={(e) =>
                                        setAddressLine(e.target.value)
                                    }
                                    placeholder="Colonia, calle, bloque y número de casa o edificio"
                                    className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                                />
                                {fieldErrors[
                                    'shipping_address.address_line'
                                ]?.[0] && (
                                    <p className="mt-1 text-xs text-rose-400">
                                        {
                                            fieldErrors[
                                                'shipping_address.address_line'
                                            ][0]
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label
                                    htmlFor="reference"
                                    className="text-xs text-zinc-300"
                                >
                                    Puntos de Referencia / Instrucciones
                                    (Opcional)
                                </Label>
                                <Input
                                    id="reference"
                                    maxLength={255}
                                    value={reference}
                                    onChange={(e) =>
                                        setReference(e.target.value)
                                    }
                                    placeholder="Frente a parque central, portón negro..."
                                    className="mt-1.5 border-zinc-800 bg-zinc-950 text-sm text-zinc-100"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Resumen Cotizado en Vivo */}
                <div className="lg:col-span-5">
                    <div className="sticky top-24 space-y-4 rounded-2xl border border-zinc-800 bg-[#121215] p-5 sm:p-6">
                        <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                            <h2 className="text-sm font-semibold text-zinc-100">
                                Resumen de tu Pedido
                            </h2>
                            {isQuoting && (
                                <Loader2 className="size-3.5 animate-spin text-primary" />
                            )}
                        </div>

                        <div className="max-h-60 divide-y divide-zinc-800/70 overflow-y-auto pr-1">
                            {items.map((item) => {
                                const quoted = quote?.items.find(
                                    (q) => q.variant_id === item.variantId,
                                );
                                return (
                                    <div
                                        key={item.variantId}
                                        className="flex items-center justify-between gap-3 py-2.5 text-xs"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-zinc-200">
                                                {item.quantity} ×{' '}
                                                {quoted?.product_name ??
                                                    item.productName}
                                            </p>
                                            <p className="font-mono text-[10px] text-zinc-500">
                                                {item.sku}
                                                {item.variantLabel
                                                    ? ` · ${item.variantLabel}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <span className="font-mono font-semibold text-zinc-100">
                                            {formatMoney(
                                                quoted?.line_total ??
                                                    item.unitPrice *
                                                        item.quantity,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Cupón Rápido */}
                        <div className="border-t border-zinc-800 pt-3">
                            <label className="flex items-center gap-1.5 text-[11px] text-zinc-400">
                                <Tag className="size-3 text-primary" />
                                Código de Cupón / Afiliado
                            </label>
                            <div className="mt-1.5 flex gap-2">
                                <Input
                                    value={couponInput}
                                    onChange={(e) =>
                                        setCouponInput(
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="KINETIC10"
                                    className="h-8 border-zinc-800 bg-zinc-950 font-mono text-xs text-zinc-100 uppercase"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    onClick={() => setCouponCode(couponInput)}
                                    className="h-8 border border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                                >
                                    Aplicar
                                </Button>
                            </div>
                            {couponCode &&
                                quote &&
                                !quote.summary.coupon.applied && (
                                    <p className="mt-1.5 text-[11px] text-amber-300">
                                        {translateCouponRejection(
                                            quote.summary.coupon.rejection_code,
                                            quote.summary.coupon.message,
                                        )}
                                    </p>
                                )}
                        </div>

                        {/* Totales */}
                        <div className="space-y-2 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-xs">
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
                                        {quote.summary.auto_promotion.name}
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

                            <div className="flex justify-between text-zinc-400">
                                <span>Envío</span>
                                <span className="font-mono text-zinc-200">
                                    {quote?.summary.shipping.is_free ? (
                                        <span className="font-semibold text-emerald-400">
                                            GRATIS
                                        </span>
                                    ) : (
                                        formatMoney(
                                            quote?.summary.shipping
                                                .shipping_fee ?? 0,
                                            currencyCode,
                                        )
                                    )}
                                </span>
                            </div>

                            <div className="flex items-baseline justify-between border-t border-zinc-800 pt-2.5">
                                <span className="font-semibold text-zinc-100">
                                    Total Neto
                                </span>
                                <span className="font-mono text-lg font-bold text-zinc-50">
                                    {formatMoney(
                                        quote?.summary.total_net ??
                                            localSubtotal,
                                        currencyCode,
                                    )}
                                </span>
                            </div>
                        </div>

                        {submitError && (
                            <div className="flex items-start gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <span>{submitError}</span>
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                isSubmitting ||
                                isQuoting ||
                                (quote !== null && !quote.is_valid)
                            }
                            className="h-12 w-full gap-2 bg-emerald-500 text-sm font-bold text-zinc-950 hover:bg-emerald-400 disabled:bg-zinc-800 disabled:text-zinc-500"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    <span>Reservando orden...</span>
                                </>
                            ) : (
                                <>
                                    <MessageCircle className="size-4" />
                                    <span>
                                        Confirmar y Continuar en WhatsApp
                                    </span>
                                </>
                            )}
                        </Button>

                        <p className="text-center text-[11px] leading-relaxed text-zinc-500">
                            Al confirmar, reservamos tu stock y abrimos WhatsApp
                            con el número de orden oficial. No realizamos cobros
                            automáticos con tarjeta.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    );
}

export default function StorefrontCheckoutPage(props: PortalShellPageProps) {
    return (
        <StorefrontLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
        >
            <CheckoutContent />
        </StorefrontLayout>
    );
}
