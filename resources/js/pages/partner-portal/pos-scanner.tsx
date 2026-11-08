import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Gift,
    Loader2,
    RotateCcw,
    Search,
    Sparkles,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PartnerPosLayout } from '@/layouts/partner-pos-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import { formatDateTime, voucherStatusMeta } from '@/lib/formatters';
import {
    type PortalShellPageProps,
    type VoucherInspectionResponse,
} from '@/types/commerce';

interface RecentRedemption {
    code: string;
    customerName: string;
    redeemedAt: string;
}

export default function PartnerPosScannerPage(props: PortalShellPageProps) {
    const [codeInput, setCodeInput] = useState<string>('');
    const [inspected, setInspected] =
        useState<VoucherInspectionResponse | null>(null);
    const [lookupError, setLookupError] = useState<string | null>(null);
    const [isInspecting, setIsInspecting] = useState<boolean>(false);
    const [isRedeeming, setIsRedeeming] = useState<boolean>(false);
    const [recentRedemptions, setRecentRedemptions] = useState<
        RecentRedemption[]
    >([]);

    const handleInspect = async (e: React.FormEvent) => {
        e.preventDefault();
        const normalized = codeInput.trim().toUpperCase();
        if (!normalized || isInspecting) {
            return;
        }

        setIsInspecting(true);
        setLookupError(null);
        setInspected(null);

        try {
            const res = await commerceApi.partner.inspectVoucher(
                props.tenantSlug,
                normalized,
            );
            setInspected(res);
        } catch (err) {
            if (err instanceof ApiClientError) {
                setLookupError(err.message);
            } else {
                setLookupError('Error de conexión al verificar el código.');
            }
        } finally {
            setIsInspecting(false);
        }
    };

    const handleRedeem = async () => {
        if (!inspected || !inspected.is_redeemable || isRedeeming) {
            return;
        }

        setIsRedeeming(true);
        setLookupError(null);

        try {
            const res = await commerceApi.partner.redeemVoucher(
                props.tenantSlug,
                inspected.voucher_code,
            );

            toast.success(res.message);
            setInspected({
                ...inspected,
                status: 'CANJEADO',
                is_redeemable: false,
                redeemed_at: res.redeemed_at,
            });
            setRecentRedemptions((prev) => [
                {
                    code: res.voucher_code,
                    customerName: inspected.customer_name,
                    redeemedAt: res.redeemed_at,
                },
                ...prev.slice(0, 4),
            ]);
        } catch (err) {
            if (err instanceof ApiClientError) {
                setLookupError(err.message);
                const refreshed = await commerceApi.partner
                    .inspectVoucher(props.tenantSlug, inspected.voucher_code)
                    .catch(() => null);
                if (refreshed) {
                    setInspected(refreshed);
                }
            } else {
                setLookupError('No fue posible completar la redención.');
            }
        } finally {
            setIsRedeeming(false);
        }
    };

    const handleReset = () => {
        setCodeInput('');
        setInspected(null);
        setLookupError(null);
    };

    return (
        <PartnerPosLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            urlMode={props.urlMode}
        >
            {(session) => (
                <div className="space-y-6">
                    <Head title={`POS · ${session.partner_store.name}`} />

                    {/* Banner Beneficio Activo */}
                    <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-4">
                        <div className="flex items-center gap-2 text-xs font-medium text-emerald-400">
                            <Sparkles className="size-3.5" />
                            <span>Beneficio autorizado para canje</span>
                        </div>
                        <p className="mt-1 text-sm font-semibold text-zinc-100">
                            {session.partner_store.benefit_title}
                        </p>
                    </div>

                    {/* Formulario de Consulta Rápida */}
                    <form
                        onSubmit={(e) => void handleInspect(e)}
                        className="rounded-2xl border border-zinc-800 bg-[#121215] p-5"
                    >
                        <label
                            htmlFor="voucher_code"
                            className="block text-xs font-semibold tracking-wider text-zinc-400 uppercase"
                        >
                            Código de Voucher del Cliente
                        </label>

                        <div className="mt-2 flex flex-col gap-2.5 sm:flex-row">
                            <Input
                                id="voucher_code"
                                required
                                autoFocus
                                value={codeInput}
                                onChange={(e) =>
                                    setCodeInput(e.target.value.toUpperCase())
                                }
                                placeholder="RWD-XXXX-XXXX"
                                className="h-12 flex-1 border-zinc-700 bg-zinc-950 text-center font-mono text-base font-bold tracking-widest text-zinc-50 uppercase placeholder:text-zinc-600"
                            />
                            <Button
                                type="submit"
                                disabled={
                                    isInspecting ||
                                    codeInput.trim().length === 0
                                }
                                className="h-12 gap-2 bg-indigo-500 px-5 text-sm font-semibold text-white hover:bg-indigo-400"
                            >
                                {isInspecting ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : (
                                    <Search className="size-4" />
                                )}
                                <span>Verificar</span>
                            </Button>
                        </div>

                        {lookupError && (
                            <div className="mt-3 flex items-start gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <span>{lookupError}</span>
                            </div>
                        )}
                    </form>

                    {/* Tarjeta de Inspección y Botón Táctil de Redención */}
                    {inspected && (
                        <div className="space-y-4 rounded-2xl border border-zinc-800 bg-[#121215] p-5">
                            <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                                <span className="font-mono text-base font-bold text-zinc-50">
                                    {inspected.voucher_code}
                                </span>
                                <span
                                    className={`rounded-full border px-3 py-1 font-mono text-xs font-bold ${voucherStatusMeta(inspected.status).badgeClass}`}
                                >
                                    {voucherStatusMeta(inspected.status).label}
                                </span>
                            </div>

                            <div className="space-y-3 text-xs">
                                <div className="rounded-xl border border-zinc-800/80 bg-zinc-950/80 p-3.5">
                                    <p className="flex items-center gap-1.5 text-[11px] text-zinc-500 uppercase">
                                        <Gift className="size-3.5 text-emerald-400" />
                                        Beneficio a Entregar
                                    </p>
                                    <p className="mt-1 text-base font-bold text-emerald-300">
                                        {inspected.benefit_title}
                                    </p>
                                    <p className="mt-1 text-zinc-400">
                                        {inspected.terms_conditions}
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                                        <p className="flex items-center gap-1 text-[10px] text-zinc-500 uppercase">
                                            <User className="size-3" />
                                            Cliente Titular
                                        </p>
                                        <p className="mt-1 font-semibold text-zinc-100">
                                            {inspected.customer_name}
                                        </p>
                                        <p className="text-[11px] text-zinc-500">
                                            Origen:{' '}
                                            {inspected.origin_tenant_name}
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                                        <p className="flex items-center gap-1 text-[10px] text-zinc-500 uppercase">
                                            <Clock className="size-3" />
                                            Vigencia
                                        </p>
                                        <p className="mt-1 font-mono text-zinc-200">
                                            Expira:{' '}
                                            {formatDateTime(
                                                inspected.expires_at,
                                            )}
                                        </p>
                                        {inspected.redeemed_at && (
                                            <p className="mt-0.5 font-mono text-[11px] text-indigo-300">
                                                Canjeado:{' '}
                                                {formatDateTime(
                                                    inspected.redeemed_at,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {inspected.is_redeemable ? (
                                <Button
                                    type="button"
                                    disabled={isRedeeming}
                                    onClick={() => void handleRedeem()}
                                    className="h-14 w-full gap-2 bg-emerald-500 text-base font-bold text-zinc-950 hover:bg-emerald-400"
                                >
                                    {isRedeeming ? (
                                        <>
                                            <Loader2 className="size-5 animate-spin" />
                                            <span>Registrando canje...</span>
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle2 className="size-5" />
                                            <span>
                                                Confirmar y Canjear Beneficio
                                            </span>
                                        </>
                                    )}
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleReset}
                                    className="h-11 w-full gap-2 border-zinc-700 bg-zinc-900 text-xs text-zinc-200"
                                >
                                    <RotateCcw className="size-4" />
                                    <span>Escanear Otro Código</span>
                                </Button>
                            )}
                        </div>
                    )}

                    {/* Bitácora Reciente de Turno */}
                    {recentRedemptions.length > 0 && (
                        <div className="rounded-2xl border border-zinc-800 bg-[#121215] p-4">
                            <h2 className="mb-2.5 text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                                Canjes en esta Sesión
                            </h2>
                            <div className="divide-y divide-zinc-800/80">
                                {recentRedemptions.map((item) => (
                                    <div
                                        key={item.code}
                                        className="flex items-center justify-between py-2 text-xs"
                                    >
                                        <div>
                                            <p className="font-mono font-bold text-emerald-400">
                                                {item.code}
                                            </p>
                                            <p className="text-[11px] text-zinc-400">
                                                {item.customerName}
                                            </p>
                                        </div>
                                        <span className="font-mono text-[11px] text-zinc-500">
                                            {formatDateTime(item.redeemedAt)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </PartnerPosLayout>
    );
}
