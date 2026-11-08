import { Head } from '@inertiajs/react';
import { Banknote, CheckCircle2, Loader2, Plus, Users } from 'lucide-react';
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
import {
    commissionStatusMeta,
    formatDateTime,
    formatMoney,
} from '@/lib/formatters';
import {
    type Affiliate,
    type AffiliateCommissionsLedgerResponse,
    type AffiliateCommissionType,
    type PortalShellPageProps,
} from '@/types/commerce';

function AffiliatesAdminContent() {
    const { tenantSlug, tenantName, currencyCode } = useTenant();

    const [affiliates, setAffiliates] = useState<Affiliate[]>([]);
    const [selectedAffiliateId, setSelectedAffiliateId] = useState<string>('');
    const [ledger, setLedger] =
        useState<AffiliateCommissionsLedgerResponse | null>(null);
    const [selectedCommissionIds, setSelectedCommissionIds] = useState<
        string[]
    >([]);
    const [paymentRef, setPaymentRef] = useState<string>('');
    const [receiptUrl, setReceiptUrl] = useState<string>('');

    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isLoadingLedger, setIsLoadingLedger] = useState<boolean>(false);
    const [isSettling, setIsSettling] = useState<boolean>(false);

    // Nuevo Afiliado
    const [createModalOpen, setCreateModalOpen] = useState<boolean>(false);
    const [affName, setAffName] = useState<string>('');
    const [affEmail, setAffEmail] = useState<string>('');
    const [affPhone, setAffPhone] = useState<string>('');
    const [commType, setCommType] =
        useState<AffiliateCommissionType>('PERCENTAGE');
    const [commValue, setCommValue] = useState<string>('10');
    const [isSavingAff, setIsSavingAff] = useState<boolean>(false);

    const loadLedger = useCallback(
        async (affiliateId: string) => {
            if (!affiliateId) {
                setLedger(null);
                return;
            }
            setIsLoadingLedger(true);
            setSelectedCommissionIds([]);
            try {
                const res = await commerceApi.admin.getAffiliateCommissions(
                    tenantSlug,
                    affiliateId,
                );
                setLedger(res);
            } catch (err) {
                toast.error(
                    err instanceof ApiClientError
                        ? err.message
                        : 'Error al consultar el libro mayor de comisiones.',
                );
            } finally {
                setIsLoadingLedger(false);
            }
        },
        [tenantSlug],
    );

    const loadAffiliates = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await commerceApi.admin.getAffiliates(tenantSlug);
            setAffiliates(res.data);
            const targetId = selectedAffiliateId || res.data[0]?.id || '';
            if (targetId) {
                setSelectedAffiliateId(targetId);
                await loadLedger(targetId);
            }
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al cargar afiliados.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [tenantSlug, selectedAffiliateId, loadLedger]);

    useEffect(() => {
        void loadAffiliates();
    }, [loadAffiliates]);

    const handleCreateAffiliate = async (e: React.FormEvent) => {
        e.preventDefault();
        if (isSavingAff) {
            return;
        }
        setIsSavingAff(true);
        try {
            const created = await commerceApi.admin.createAffiliate(
                tenantSlug,
                {
                    name: affName.trim(),
                    email: affEmail.trim(),
                    phone: affPhone.trim(),
                    commission_type: commType,
                    commission_value: Number(commValue),
                    is_active: true,
                },
            );
            toast.success(`Afiliado ${created.data.name} registrado.`);
            setCreateModalOpen(false);
            setAffName('');
            setAffEmail('');
            setAffPhone('');
            setSelectedAffiliateId(created.data.id);
            await loadAffiliates();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'No se pudo registrar el afiliado.',
            );
        } finally {
            setIsSavingAff(false);
        }
    };

    const toggleCommissionSelection = (id: string) => {
        setSelectedCommissionIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const handleSettle = async (e: React.FormEvent) => {
        e.preventDefault();
        if (
            !selectedAffiliateId ||
            selectedCommissionIds.length === 0 ||
            !paymentRef.trim() ||
            isSettling
        ) {
            return;
        }

        setIsSettling(true);
        try {
            const res = await commerceApi.admin.settleAffiliateCommissions(
                tenantSlug,
                selectedAffiliateId,
                {
                    commission_ids: selectedCommissionIds,
                    payment_reference: paymentRef.trim(),
                    payment_receipt_url: receiptUrl.trim() || null,
                },
            );
            toast.success(
                `${res.settled_count} comisiones liquidadas por ${formatMoney(res.total_paid, currencyCode)}.`,
            );
            setPaymentRef('');
            setReceiptUrl('');
            await loadLedger(selectedAffiliateId);
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'Error al liquidar comisiones.',
            );
        } finally {
            setIsSettling(false);
        }
    };

    return (
        <div>
            <Head title={`Afiliados y Liquidación · ${tenantName}`} />

            <div className="mb-6 flex items-center justify-between">
                <p className="text-xs text-zinc-400">
                    Las comisiones pasan a{' '}
                    <strong className="text-indigo-300">APROBADA</strong> cuando
                    la orden vinculada se marca como COMPLETADA.
                </p>
                <Button
                    type="button"
                    size="sm"
                    onClick={() => setCreateModalOpen(true)}
                    className="h-9 bg-indigo-500 text-xs font-semibold text-white"
                >
                    <Plus className="mr-1.5 size-3.5" />
                    Nuevo Afiliado
                </Button>
            </div>

            {isLoading ? (
                <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                    <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                    Cargando red de afiliados...
                </div>
            ) : affiliates.length === 0 ? (
                <div className="rounded-xl border border-dashed border-zinc-800 bg-[#121215]/60 p-12 text-center">
                    <Users className="mx-auto size-10 text-zinc-600" />
                    <p className="mt-3 text-sm font-medium text-zinc-200">
                        Aún no tienes creadores o afiliados registrados
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                    {/* Lista de Afiliados */}
                    <div className="space-y-2.5 xl:col-span-4">
                        {affiliates.map((aff) => {
                            const active = aff.id === selectedAffiliateId;
                            return (
                                <button
                                    key={aff.id}
                                    type="button"
                                    onClick={() => {
                                        setSelectedAffiliateId(aff.id);
                                        void loadLedger(aff.id);
                                    }}
                                    className={`w-full rounded-xl border p-4 text-left transition ${
                                        active
                                            ? 'border-primary bg-[#18181b] ring-1 ring-primary/40'
                                            : 'border-zinc-800 bg-[#121215] hover:border-zinc-700'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-semibold text-zinc-100">
                                            {aff.name}
                                        </span>
                                        <span className="rounded bg-indigo-500/15 px-2 py-0.5 font-mono text-[10px] text-indigo-300">
                                            {aff.commission_type ===
                                            'PERCENTAGE'
                                                ? `${Number(aff.commission_value)}%`
                                                : formatMoney(
                                                      aff.commission_value,
                                                      currencyCode,
                                                  )}
                                        </span>
                                    </div>
                                    <p className="mt-1 font-mono text-xs text-zinc-500">
                                        {aff.email}
                                    </p>
                                </button>
                            );
                        })}
                    </div>

                    {/* Ledger y Liquidación */}
                    <div className="space-y-4 xl:col-span-8">
                        {isLoadingLedger || !ledger ? (
                            <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                                <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                                Consultando libro mayor...
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <div className="rounded-xl border border-zinc-800 bg-[#121215] p-3.5">
                                        <p className="text-[10px] text-amber-300 uppercase">
                                            Retenidas
                                        </p>
                                        <p className="mt-1 font-mono text-base font-bold text-zinc-50">
                                            {formatMoney(
                                                ledger.totals.retained,
                                                currencyCode,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-indigo-500/30 bg-indigo-500/10 p-3.5">
                                        <p className="text-[10px] text-indigo-300 uppercase">
                                            Aprobadas (Liquidables)
                                        </p>
                                        <p className="mt-1 font-mono text-base font-bold text-indigo-100">
                                            {formatMoney(
                                                ledger.totals.approved,
                                                currencyCode,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5">
                                        <p className="text-[10px] text-emerald-300 uppercase">
                                            Pagadas
                                        </p>
                                        <p className="mt-1 font-mono text-base font-bold text-emerald-100">
                                            {formatMoney(
                                                ledger.totals.paid,
                                                currencyCode,
                                            )}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-zinc-800 bg-[#121215] p-3.5">
                                        <p className="text-[10px] text-zinc-500 uppercase">
                                            Anuladas
                                        </p>
                                        <p className="mt-1 font-mono text-base font-bold text-zinc-400">
                                            {formatMoney(
                                                ledger.totals.voided,
                                                currencyCode,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-xl border border-zinc-800 bg-[#121215] p-4">
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider text-zinc-300 uppercase">
                                        Movimientos de Comisión
                                    </h3>

                                    {ledger.commissions.data.length === 0 ? (
                                        <p className="py-6 text-center text-xs text-zinc-500">
                                            Este afiliado aún no registra
                                            órdenes con sus cupones.
                                        </p>
                                    ) : (
                                        <div className="divide-y divide-zinc-800/80">
                                            {ledger.commissions.data.map(
                                                (comm) => {
                                                    const canSettle =
                                                        comm.status ===
                                                        'APROBADA';
                                                    const checked =
                                                        selectedCommissionIds.includes(
                                                            comm.id,
                                                        );
                                                    const badge =
                                                        commissionStatusMeta(
                                                            comm.status,
                                                        );

                                                    return (
                                                        <div
                                                            key={comm.id}
                                                            className="flex items-center justify-between gap-3 py-3 text-xs"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                {canSettle && (
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={
                                                                            checked
                                                                        }
                                                                        onChange={() =>
                                                                            toggleCommissionSelection(
                                                                                comm.id,
                                                                            )
                                                                        }
                                                                        className="rounded border-zinc-700 bg-zinc-900"
                                                                    />
                                                                )}
                                                                <div>
                                                                    <p className="font-mono font-semibold text-zinc-100">
                                                                        {comm
                                                                            .order
                                                                            ?.order_number ??
                                                                            comm.order_id.slice(
                                                                                0,
                                                                                8,
                                                                            )}{' '}
                                                                        {comm.coupon
                                                                            ? `· ${comm.coupon.code}`
                                                                            : ''}
                                                                    </p>
                                                                    <p className="text-[11px] text-zinc-500">
                                                                        {formatDateTime(
                                                                            comm.created_at,
                                                                        )}
                                                                        {comm.payment_reference
                                                                            ? ` · Ref: ${comm.payment_reference}`
                                                                            : ''}
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <div className="flex items-center gap-2.5">
                                                                <span
                                                                    className={`rounded-full border px-2 py-0.5 text-[10px] ${badge.badgeClass}`}
                                                                >
                                                                    {
                                                                        badge.label
                                                                    }
                                                                </span>
                                                                <span className="font-mono font-bold text-zinc-100">
                                                                    {formatMoney(
                                                                        comm.amount,
                                                                        currencyCode,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    );
                                                },
                                            )}
                                        </div>
                                    )}

                                    {selectedCommissionIds.length > 0 && (
                                        <form
                                            onSubmit={(e) =>
                                                void handleSettle(e)
                                            }
                                            className="mt-4 space-y-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4"
                                        >
                                            <div className="flex items-center gap-2 text-xs font-semibold text-emerald-300">
                                                <Banknote className="size-4" />
                                                <span>
                                                    Liquidar{' '}
                                                    {
                                                        selectedCommissionIds.length
                                                    }{' '}
                                                    comisión(es) seleccionada(s)
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                                <Input
                                                    required
                                                    value={paymentRef}
                                                    onChange={(e) =>
                                                        setPaymentRef(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Referencia bancaria / # comprobante *"
                                                    className="h-8 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                                />
                                                <Input
                                                    value={receiptUrl}
                                                    onChange={(e) =>
                                                        setReceiptUrl(
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="URL comprobante (opcional)"
                                                    className="h-8 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={isSettling}
                                                className="h-9 gap-1.5 bg-emerald-500 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
                                            >
                                                <CheckCircle2 className="size-3.5" />
                                                <span>
                                                    {isSettling
                                                        ? 'Registrando liquidación...'
                                                        : 'Confirmar Liquidación'}
                                                </span>
                                            </Button>
                                        </form>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            {/* Modal Nuevo Afiliado */}
            <Dialog open={createModalOpen} onOpenChange={setCreateModalOpen}>
                <DialogContent className="border-zinc-800 bg-[#121215] text-zinc-100 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Registrar Afiliado / Embajador
                        </DialogTitle>
                        <DialogDescription className="text-xs text-zinc-400">
                            Luego podrás emitirle cupones exclusivos en Promos y
                            Cupones.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => void handleCreateAffiliate(e)}
                        className="space-y-3"
                    >
                        <div>
                            <Label className="text-xs text-zinc-300">
                                Nombre Completo *
                            </Label>
                            <Input
                                required
                                value={affName}
                                onChange={(e) => setAffName(e.target.value)}
                                placeholder="Ej. Carlos Rivera Fitness"
                                className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Correo *
                                </Label>
                                <Input
                                    type="email"
                                    required
                                    value={affEmail}
                                    onChange={(e) =>
                                        setAffEmail(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 text-xs"
                                />
                            </div>
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Teléfono / WhatsApp *
                                </Label>
                                <Input
                                    required
                                    value={affPhone}
                                    onChange={(e) =>
                                        setAffPhone(e.target.value)
                                    }
                                    placeholder="+504 9888-7777"
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Esquema Comisión
                                </Label>
                                <select
                                    value={commType}
                                    onChange={(e) =>
                                        setCommType(
                                            e.target
                                                .value as AffiliateCommissionType,
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
                                </select>
                            </div>
                            <div>
                                <Label className="text-xs text-zinc-300">
                                    Valor Comisión *
                                </Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={commValue}
                                    onChange={(e) =>
                                        setCommValue(e.target.value)
                                    }
                                    className="mt-1 border-zinc-800 bg-zinc-950 font-mono text-xs"
                                />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            disabled={isSavingAff}
                            className="w-full bg-indigo-500 text-xs font-semibold text-white"
                        >
                            {isSavingAff
                                ? 'Guardando...'
                                : 'Registrar Afiliado'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default function TenantAdminAffiliatesPage(props: PortalShellPageProps) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="affiliates"
            title="Afiliados y Liquidación de Comisiones"
            subtitle="Libro mayor de atribuciones, estados de retención y dispersión de pagos"
        >
            <AffiliatesAdminContent />
        </TenantAdminLayout>
    );
}
