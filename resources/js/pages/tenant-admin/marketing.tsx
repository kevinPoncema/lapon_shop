import { Head } from '@inertiajs/react';
import { Copy, Loader2, Plus, Sparkles, Tag } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/contexts/tenant-context';
import { TenantAdminLayout } from '@/layouts/tenant-admin-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import { formatDateTime, formatMoney } from '@/lib/formatters';
import { portalPaths } from '@/lib/portal-paths';
import {
    type Affiliate,
    type Coupon,
    type DiscountType,
    type PortalShellPageProps,
    type Promotion,
    type PromotionType,
} from '@/types/commerce';

function todayIsoDate(): string {
    return new Date().toISOString().slice(0, 10);
}

function MarketingAdminContent() {
    const { tenantSlug, tenantName, currencyCode, routeCtx } = useTenant();

    const [promotions, setPromotions] = useState<Promotion[]>([]);
    const [coupons, setCoupons] = useState<Coupon[]>([]);
    const [affiliates, setAffiliates] = useState<Affiliate[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);

    // Modal Promoción
    const [promoModalOpen, setPromoModalOpen] = useState<boolean>(false);
    const [promoName, setPromoName] = useState<string>('');
    const [promoType, setPromoType] = useState<PromotionType>('AUTOMATIC');
    const [discountType, setDiscountType] =
        useState<DiscountType>('PERCENTAGE');
    const [discountValue, setDiscountValue] = useState<string>('10');
    const [minSubtotal, setMinSubtotal] = useState<string>('0');
    const [allowStacking, setAllowStacking] = useState<boolean>(true);
    const [startsAt, setStartsAt] = useState<string>(todayIsoDate());
    const [isSavingPromo, setIsSavingPromo] = useState<boolean>(false);

    // Modal Cupón
    const [couponModalOpen, setCouponModalOpen] = useState<boolean>(false);
    const [couponCode, setCouponCode] = useState<string>('');
    const [couponPromoId, setCouponPromoId] = useState<string>('');
    const [couponAffiliateId, setCouponAffiliateId] = useState<string>('');
    const [couponMaxUses, setCouponMaxUses] = useState<string>('');
    const [couponStartsAt, setCouponStartsAt] =
        useState<string>(todayIsoDate());
    const [isSavingCoupon, setIsSavingCoupon] = useState<boolean>(false);

    const loadMarketing = useCallback(async () => {
        setIsLoading(true);
        try {
            const [promoRes, couponRes, affRes] = await Promise.all([
                commerceApi.admin.getPromotions(tenantSlug),
                commerceApi.admin.getCoupons(tenantSlug),
                commerceApi.admin.getAffiliates(tenantSlug),
            ]);
            setPromotions(promoRes.data);
            setCoupons(couponRes.data);
            setAffiliates(affRes.data);
            if (!couponPromoId && promoRes.data[0]?.id) {
                setCouponPromoId(promoRes.data[0].id);
            }
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar promociones y cupones.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [tenantSlug, couponPromoId]);

    useEffect(() => {
        void loadMarketing();
    }, [loadMarketing]);

    const handleCreatePromotion = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSavingPromo) {
            return;
        }
        setIsSavingPromo(true);
        try {
            await commerceApi.admin.createPromotion(tenantSlug, {
                name: promoName.trim(),
                type: promoType,
                discount_type: discountType,
                discount_value:
                    discountType === 'FREE_SHIPPING'
                        ? 0
                        : Number(discountValue),
                conditions:
                    Number(minSubtotal) > 0
                        ? { min_subtotal: Number(minSubtotal) }
                        : {},
                allow_stacking: allowStacking,
                starts_at: new Date(startsAt).toISOString(),
                is_active: true,
            });
            toast.success('Regla promocional creada.');
            setPromoModalOpen(false);
            setPromoName('');
            await loadMarketing();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al crear promoción.',
            );
        } finally {
            setIsSavingPromo(false);
        }
    };

    const handleTogglePromotion = async (promo: Promotion) => {
        try {
            await commerceApi.admin.updatePromotion(tenantSlug, promo.id, {
                is_active: !promo.is_active,
            });
            toast.success(
                !promo.is_active
                    ? 'Promoción activada.'
                    : 'Promoción desactivada.',
            );
            await loadMarketing();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'No se pudo actualizar la promoción.',
            );
        }
    };

    const handleCreateCoupon = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSavingCoupon || !couponPromoId) {
            return;
        }
        setIsSavingCoupon(true);
        try {
            await commerceApi.admin.createCoupon(tenantSlug, {
                promotion_id: couponPromoId,
                affiliate_id: couponAffiliateId || null,
                code: couponCode.trim().toUpperCase(),
                max_uses: couponMaxUses ? Number(couponMaxUses) : null,
                starts_at: new Date(couponStartsAt).toISOString(),
                is_active: true,
            });
            toast.success('Cupón emitido exitosamente.');
            setCouponModalOpen(false);
            setCouponCode('');
            await loadMarketing();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al emitir cupón.',
            );
        } finally {
            setIsSavingCoupon(false);
        }
    };

    const copyShareUrl = async (code: string) => {
        if (typeof window === 'undefined') {
            return;
        }
        const path = portalPaths.storefront.catalog(routeCtx, {
            query: { coupon: code },
        });
        const fullUrl = `${window.location.origin}${path}`;
        try {
            await navigator.clipboard.writeText(fullUrl);
            toast.success(`Enlace promocional copiado: ${fullUrl}`);
        } catch {
            toast.error('No se pudo copiar el enlace.');
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                Cargando reglas comerciales...
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <Head title={`Promociones y Cupones · ${tenantName}`} />

            {/* Columna Promociones */}
            <section className="space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div className="flex items-center gap-2">
                        <Sparkles className="size-4 text-indigo-400" />
                        <h2 className="text-sm font-semibold text-zinc-100">
                            Motor de Promociones ({promotions.length})
                        </h2>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setPromoModalOpen(true)}
                        className="h-8 bg-indigo-500 text-xs font-semibold text-white"
                    >
                        <Plus className="mr-1 size-3.5" />
                        Nueva Regla
                    </Button>
                </div>

                {promotions.length === 0 ? (
                    <p className="py-8 text-center text-xs text-zinc-500">
                        Sin reglas promocionales creadas.
                    </p>
                ) : (
                    <div className="space-y-2.5">
                        {promotions.map((promo) => (
                            <div
                                key={promo.id}
                                className="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-xs"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold text-zinc-100">
                                            {promo.name}
                                        </span>
                                        <span className="rounded border border-zinc-800 bg-zinc-900 px-1.5 py-0.5 font-mono text-[10px] text-zinc-400">
                                            {promo.type}
                                        </span>
                                    </div>
                                    <p className="mt-1 font-mono text-[11px] text-emerald-400">
                                        {promo.discount_type === 'PERCENTAGE'
                                            ? `${Number(promo.discount_value)}% OFF`
                                            : promo.discount_type ===
                                                'FREE_SHIPPING'
                                              ? 'ENVÍO GRATIS'
                                              : `-${formatMoney(promo.discount_value, currencyCode)}`}
                                        {promo.conditions?.min_subtotal
                                            ? ` · Mín: ${formatMoney(promo.conditions.min_subtotal, currencyCode)}`
                                            : ''}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    onClick={() =>
                                        void handleTogglePromotion(promo)
                                    }
                                    className={`rounded-full border px-2.5 py-1 font-mono text-[10px] font-semibold ${
                                        promo.is_active
                                            ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300'
                                            : 'border-zinc-700 bg-zinc-900 text-zinc-500'
                                    }`}
                                >
                                    {promo.is_active ? 'ACTIVA' : 'PAUSADA'}
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {/* Columna Cupones */}
            <section className="space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                    <div className="flex items-center gap-2">
                        <Tag className="size-4 text-emerald-400" />
                        <h2 className="text-sm font-semibold text-zinc-100">
                            Cupones y Códigos de Afiliado ({coupons.length})
                        </h2>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        disabled={promotions.length === 0}
                        onClick={() => setCouponModalOpen(true)}
                        className="h-8 bg-emerald-500 text-xs font-semibold text-zinc-950 hover:bg-emerald-400"
                    >
                        <Plus className="mr-1 size-3.5" />
                        Emitir Cupón
                    </Button>
                </div>

                {coupons.length === 0 ? (
                    <p className="py-8 text-center text-xs text-zinc-500">
                        Crea una promoción tipo COUPON y emite tu primer código
                        compartible.
                    </p>
                ) : (
                    <div className="space-y-2.5">
                        {coupons.map((c) => (
                            <div
                                key={c.id}
                                className="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-950/70 p-3.5 text-xs"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="rounded border border-indigo-500/30 bg-indigo-500/15 px-2 py-0.5 font-mono text-xs font-bold text-indigo-200">
                                            {c.code}
                                        </span>
                                        <span className="font-mono text-[11px] text-zinc-400">
                                            Usos: {c.used_count}
                                            {c.max_uses
                                                ? ` / ${c.max_uses}`
                                                : ' (∞)'}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-[11px] text-zinc-400">
                                        {c.promotion?.name ?? 'Promoción'}
                                        {c.affiliate
                                            ? ` · Afiliado: ${c.affiliate.name}`
                                            : ''}
                                    </p>
                                    <p className="text-[10px] text-zinc-600">
                                        Desde: {formatDateTime(c.starts_at)}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void copyShareUrl(c.code)}
                                    className="h-8 border-zinc-800 bg-zinc-900 text-xs text-zinc-300"
                                >
                                    <Copy className="mr-1.5 size-3" />
                                    Link
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {/* Modal Nueva Promoción */}
            <Dialog open={promoModalOpen} onOpenChange={setPromoModalOpen}>
                <DialogContent className="border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Nueva Regla Promocional</DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Se evalúa en servidor durante el quote y checkout.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => void handleCreatePromotion(e)}
                        className="space-y-3"
                    >
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Nombre Comercial *
                            </Label>
                            <Input
                                required
                                value={promoName}
                                onChange={(e) => setPromoName(e.target.value)}
                                placeholder="Ej. Lanzamiento Kinetic 15%"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Activación
                                </Label>
                                <select
                                    value={promoType}
                                    onChange={(e) =>
                                        setPromoType(
                                            e.target.value as PromotionType,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100"
                                >
                                    <option value="AUTOMATIC">
                                        Automática en Bolsa
                                    </option>
                                    <option value="COUPON">
                                        Mediante Cupón
                                    </option>
                                </select>
                            </div>

                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Tipo de Beneficio
                                </Label>
                                <select
                                    value={discountType}
                                    onChange={(e) =>
                                        setDiscountType(
                                            e.target.value as DiscountType,
                                        )
                                    }
                                    className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100"
                                >
                                    <option value="PERCENTAGE">
                                        Porcentaje (%)
                                    </option>
                                    <option value="FIXED_AMOUNT">
                                        Monto Fijo ({currencyCode})
                                    </option>
                                    <option value="FREE_SHIPPING">
                                        Envío Gratis
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            {discountType !== 'FREE_SHIPPING' && (
                                <div>
                                    <Label className="text-xs text-zinc-300">
                                        Valor de Descuento *
                                    </Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                        value={discountValue}
                                        onChange={(e) =>
                                            setDiscountValue(e.target.value)
                                        }
                                        className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                    />
                                </div>
                            )}

                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Subtotal Mínimo ({currencyCode})
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={minSubtotal}
                                    onChange={(e) =>
                                        setMinSubtotal(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Fecha de Inicio *
                            </Label>
                            <Input
                                type="date"
                                required
                                value={startsAt}
                                onChange={(e) => setStartsAt(e.target.value)}
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>

                        <label className="flex items-center gap-2 text-xs text-zinc-300">
                            <input
                                type="checkbox"
                                checked={allowStacking}
                                onChange={(e) =>
                                    setAllowStacking(e.target.checked)
                                }
                            />
                            Permitir acumulación (stacking) con cupones
                        </label>

                        <Button
                            type="submit"
                            disabled={isSavingPromo}
                            className="w-full bg-indigo-500 text-xs font-semibold text-white"
                        >
                            {isSavingPromo ? 'Creando...' : 'Guardar Promoción'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal Emitir Cupón */}
            <Dialog open={couponModalOpen} onOpenChange={setCouponModalOpen}>
                <DialogContent className="border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Emitir Cupón de Descuento</DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Vincula opcionalmente un afiliado para atribuirle
                            comisiones automáticamente.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => void handleCreateCoupon(e)}
                        className="space-y-3"
                    >
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Código del Cupón *
                            </Label>
                            <Input
                                required
                                maxLength={50}
                                value={couponCode}
                                onChange={(e) =>
                                    setCouponCode(e.target.value.toUpperCase())
                                }
                                placeholder="KINETIC10"
                                className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs uppercase"
                            />
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Regla Promocional Base *
                            </Label>
                            <select
                                required
                                value={couponPromoId}
                                onChange={(e) =>
                                    setCouponPromoId(e.target.value)
                                }
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100"
                            >
                                {promotions.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} ({p.type})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <Label className="text-xs text-zinc-300">
                                Atribuir a Afiliado (Opcional)
                            </Label>
                            <select
                                value={couponAffiliateId}
                                onChange={(e) =>
                                    setCouponAffiliateId(e.target.value)
                                }
                                className="mt-1 h-9 w-full rounded-md border border-zinc-800 bg-zinc-950 px-2.5 text-xs text-zinc-100"
                            >
                                <option value="">
                                    Sin afiliado (cupón directo de tienda)
                                </option>
                                {affiliates.map((aff) => (
                                    <option key={aff.id} value={aff.id}>
                                        {aff.name} ({aff.email})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Límite de Usos (vacío = ∞)
                                </Label>
                                <Input
                                    type="number"
                                    min="1"
                                    value={couponMaxUses}
                                    onChange={(e) =>
                                        setCouponMaxUses(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Vigencia Desde *
                                </Label>
                                <Input
                                    type="date"
                                    required
                                    value={couponStartsAt}
                                    onChange={(e) =>
                                        setCouponStartsAt(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            disabled={isSavingCoupon}
                            className="w-full bg-emerald-500 text-xs font-bold text-zinc-950"
                        >
                            {isSavingCoupon ? 'Emitiendo...' : 'Emitir Cupón'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function TenantAdminMarketingPage(props: PortalShellPageProps) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="marketing"
            title="Promociones y Cupones"
            subtitle="Descuentos automáticos de carrito, cupones y enlaces de referido"
        >
            <MarketingAdminContent />
        </TenantAdminLayout>
    );
}
