import { Head } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Gift,
    Loader2,
    MessageCircle,
    RefreshCw,
    Search,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/contexts/tenant-context';
import { TenantAdminLayout } from '@/layouts/tenant-admin-layout';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import {
    commissionStatusMeta,
    formatDateTime,
    formatMoney,
    formatVariantAttributes,
    orderStatusMeta,
    voucherStatusMeta,
} from '@/lib/formatters';
import {
    type LaravelLengthAwarePaginator,
    type OrderModel,
    type OrderStatus,
    type PortalShellPageProps,
} from '@/types/commerce';

function OrdersHubContent() {
    const { tenantSlug, tenantName, currencyCode } = useTenant();

    const [statusFilter, setStatusFilter] = useState<OrderStatus | ''>('');
    const [searchDraft, setSearchDraft] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [page, setPage] = useState<number>(1);

    const [paginator, setPaginator] =
        useState<LaravelLengthAwarePaginator<OrderModel> | null>(null);
    const [selectedOrder, setSelectedOrder] = useState<OrderModel | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isLoadingDetail, setIsLoadingDetail] = useState<boolean>(false);

    const [paymentNotes, setPaymentNotes] = useState<string>('');
    const [cancellationReason, setCancellationReason] = useState<string>('');
    const [isTransitioning, setIsTransitioning] = useState<boolean>(false);

    const loadOrders = useCallback(async () => {
        setIsLoading(true);
        try {
            const res = await commerceApi.admin.getOrders(tenantSlug, {
                status: statusFilter || undefined,
                q: searchQuery || undefined,
                page,
                per_page: 15,
            });
            setPaginator(res);

            if (res.data.length > 0 && !selectedOrder) {
                const firstId = res.data[0]?.id;
                if (firstId) {
                    const detail = await commerceApi.admin.getOrder(
                        tenantSlug,
                        firstId,
                    );
                    setSelectedOrder(detail.data);
                }
            } else if (res.data.length === 0) {
                setSelectedOrder(null);
            }
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'No se pudieron cargar las órdenes.',
            );
        } finally {
            setIsLoading(false);
        }
    }, [tenantSlug, statusFilter, searchQuery, page, selectedOrder]);

    useEffect(() => {
        void loadOrders();
    }, [loadOrders]);

    const handleSelectOrder = async (orderId: string) => {
        setIsLoadingDetail(true);
        setPaymentNotes('');
        setCancellationReason('');
        try {
            const res = await commerceApi.admin.getOrder(tenantSlug, orderId);
            setSelectedOrder(res.data);
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? err.message
                    : 'Error al inspeccionar la orden.',
            );
        } finally {
            setIsLoadingDetail(false);
        }
    };

    const handleTransition = async (target: 'COMPLETADA' | 'CANCELADA') => {
        if (!selectedOrder || isTransitioning) {
            return;
        }

        setIsTransitioning(true);
        try {
            const res = await commerceApi.admin.updateOrderStatus(
                tenantSlug,
                selectedOrder.id,
                {
                    status: target,
                    payment_notes:
                        target === 'COMPLETADA'
                            ? paymentNotes.trim() || null
                            : undefined,
                    cancellation_reason:
                        target === 'CANCELADA'
                            ? cancellationReason.trim() || null
                            : undefined,
                },
            );

            if (target === 'COMPLETADA') {
                toast.success(
                    res.outbound_reward_generated && res.outbound_reward_code
                        ? `Orden ${res.order_number} completada. Voucher aliado emitido: ${res.outbound_reward_code}`
                        : `Orden ${res.order_number} marcada como COMPLETADA.`,
                );
            } else {
                toast.success(
                    `Orden ${res.order_number} cancelada e inventario revertido.`,
                );
            }

            const refreshed = await commerceApi.admin.getOrder(
                tenantSlug,
                selectedOrder.id,
            );
            setSelectedOrder(refreshed.data);
            setPaymentNotes('');
            setCancellationReason('');
            await loadOrders();
        } catch (err) {
            toast.error(
                err instanceof ApiClientError
                    ? (err.firstFieldError() ?? err.message)
                    : 'No fue posible actualizar el estado de la orden.',
            );
        } finally {
            setIsTransitioning(false);
        }
    };

    const orders = paginator?.data ?? [];

    return (
        <div>
            <Head title={`Órdenes WhatsApp · ${tenantName}`} />

            {/* Barra de Filtros y Búsqueda */}
            <div className="mb-6 flex flex-col gap-3 rounded-xl border border-zinc-800 bg-[#121215] p-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-wrap gap-1.5">
                    {(
                        [
                            { key: '', label: 'Todas' },
                            {
                                key: 'PENDIENTE_PAGO',
                                label: 'Pendientes Pago',
                            },
                            { key: 'COMPLETADA', label: 'Completadas' },
                            { key: 'CANCELADA', label: 'Canceladas' },
                        ] as const
                    ).map((tab) => {
                        const active = statusFilter === tab.key;
                        return (
                            <button
                                key={tab.label}
                                type="button"
                                onClick={() => {
                                    setStatusFilter(tab.key);
                                    setPage(1);
                                }}
                                className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                                    active
                                        ? 'border-primary bg-primary/20 text-zinc-50'
                                        : 'border-zinc-800 bg-zinc-950 text-zinc-400 hover:text-zinc-200'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        setSearchQuery(searchDraft.trim());
                        setPage(1);
                    }}
                    className="flex gap-2"
                >
                    <div className="relative flex-1 sm:w-64">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-zinc-500" />
                        <Input
                            value={searchDraft}
                            onChange={(e) => setSearchDraft(e.target.value)}
                            placeholder="#ORD, nombre o WhatsApp..."
                            className="h-9 border-zinc-800 bg-zinc-950 pl-8 text-xs text-zinc-100"
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        size="sm"
                        className="h-9 border border-zinc-700 bg-zinc-800 text-xs text-zinc-100"
                    >
                        Filtrar
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => void loadOrders()}
                        className="size-9 border-zinc-800 bg-zinc-950"
                        title="Refrescar órdenes"
                    >
                        <RefreshCw className="size-3.5 text-zinc-400" />
                    </Button>
                </form>
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-12">
                {/* Columna Izquierda: Lista de Órdenes */}
                <div className="space-y-3 xl:col-span-6">
                    {isLoading ? (
                        <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-16 text-xs text-zinc-400">
                            <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                            Cargando órdenes...
                        </div>
                    ) : orders.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-zinc-800 bg-[#121215]/60 p-10 text-center text-xs text-zinc-400">
                            No se encontraron órdenes con los criterios
                            actuales.
                        </div>
                    ) : (
                        orders.map((order) => {
                            const statusBadge = orderStatusMeta(order.status);
                            const isSelected = selectedOrder?.id === order.id;

                            return (
                                <button
                                    key={order.id}
                                    type="button"
                                    onClick={() =>
                                        void handleSelectOrder(order.id)
                                    }
                                    className={`w-full rounded-xl border p-4 text-left transition ${
                                        isSelected
                                            ? 'border-primary bg-[#18181b] ring-1 ring-primary/40'
                                            : 'border-zinc-800 bg-[#121215] hover:border-zinc-700'
                                    }`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono text-xs font-bold text-zinc-100">
                                            {order.order_number}
                                        </span>
                                        <span
                                            className={`rounded-full border px-2 py-0.5 text-[10px] font-medium ${statusBadge.badgeClass}`}
                                        >
                                            {statusBadge.label}
                                        </span>
                                    </div>

                                    <div className="mt-2 flex items-baseline justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-medium text-zinc-200">
                                                {order.customer_info.full_name}
                                            </p>
                                            <p className="font-mono text-[11px] text-zinc-500">
                                                {
                                                    order.customer_info
                                                        .whatsapp_phone
                                                }{' '}
                                                ·{' '}
                                                {formatDateTime(
                                                    order.created_at,
                                                )}
                                            </p>
                                        </div>
                                        <span className="font-mono text-sm font-bold text-zinc-50">
                                            {formatMoney(
                                                order.total_net,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                </button>
                            );
                        })
                    )}

                    {paginator && paginator.last_page > 1 && (
                        <div className="flex items-center justify-between pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={paginator.current_page <= 1}
                                onClick={() =>
                                    setPage((p) => Math.max(1, p - 1))
                                }
                                className="border-zinc-800 bg-[#121215] text-xs"
                            >
                                <ChevronLeft className="mr-1 size-3.5" />
                                Anterior
                            </Button>
                            <span className="font-mono text-xs text-zinc-500">
                                {paginator.current_page} / {paginator.last_page}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    paginator.current_page >=
                                    paginator.last_page
                                }
                                onClick={() => setPage((p) => p + 1)}
                                className="border-zinc-800 bg-[#121215] text-xs"
                            >
                                Siguiente
                                <ChevronRight className="ml-1 size-3.5" />
                            </Button>
                        </div>
                    )}
                </div>

                {/* Columna Derecha: Inspector y Máquina de Estados */}
                <div className="xl:col-span-6">
                    {isLoadingDetail ? (
                        <div className="flex items-center justify-center rounded-xl border border-zinc-800 bg-[#121215] py-20 text-xs text-zinc-400">
                            <Loader2 className="mr-2 size-4 animate-spin text-primary" />
                            Cargando expediente de orden...
                        </div>
                    ) : !selectedOrder ? (
                        <div className="rounded-xl border border-zinc-800 bg-[#121215] p-8 text-center text-xs text-zinc-500">
                            Selecciona una orden de la lista para gestionar su
                            pago, despacho o cancelación.
                        </div>
                    ) : (
                        <div className="space-y-4 rounded-xl border border-zinc-800 bg-[#121215] p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800 pb-4">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="font-mono text-lg font-bold text-zinc-50">
                                            {selectedOrder.order_number}
                                        </h2>
                                        <span
                                            className={`rounded-full border px-2.5 py-0.5 text-xs font-medium ${orderStatusMeta(selectedOrder.status).badgeClass}`}
                                        >
                                            {
                                                orderStatusMeta(
                                                    selectedOrder.status,
                                                ).label
                                            }
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-zinc-400">
                                        Creada:{' '}
                                        {formatDateTime(
                                            selectedOrder.created_at,
                                        )}
                                    </p>
                                </div>

                                {selectedOrder.whatsapp_url && (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="h-8 gap-1.5 bg-emerald-500 text-xs font-semibold text-zinc-950 hover:bg-emerald-400"
                                    >
                                        <a
                                            href={selectedOrder.whatsapp_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <MessageCircle className="size-3.5" />
                                            <span>Chat WhatsApp</span>
                                            <ExternalLink className="size-3" />
                                        </a>
                                    </Button>
                                )}
                            </div>

                            {/* Cliente y Envío */}
                            <div className="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                                <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                                    <p className="text-[10px] font-semibold tracking-wider text-zinc-500 uppercase">
                                        Comprador
                                    </p>
                                    <p className="mt-1 font-medium text-zinc-100">
                                        {selectedOrder.customer_info.full_name}
                                    </p>
                                    <p className="font-mono text-zinc-400">
                                        {
                                            selectedOrder.customer_info
                                                .whatsapp_phone
                                        }
                                    </p>
                                    <p className="truncate text-zinc-500">
                                        {selectedOrder.customer_info.email}
                                    </p>
                                </div>

                                <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                                    <p className="text-[10px] font-semibold tracking-wider text-zinc-500 uppercase">
                                        Entrega (
                                        {
                                            selectedOrder.shipping_address
                                                .region_code
                                        }
                                        )
                                    </p>
                                    <p className="mt-1 text-zinc-200">
                                        {
                                            selectedOrder.shipping_address
                                                .address_line
                                        }
                                    </p>
                                    {selectedOrder.shipping_address
                                        .reference && (
                                        <p className="mt-0.5 text-zinc-500">
                                            Ref:{' '}
                                            {
                                                selectedOrder.shipping_address
                                                    .reference
                                            }
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Artículos */}
                            <div className="rounded-lg border border-zinc-800/80 bg-zinc-950/60 p-3">
                                <p className="mb-2 text-[10px] font-semibold tracking-wider text-zinc-500 uppercase">
                                    Artículos ({selectedOrder.items.length})
                                </p>
                                <div className="divide-y divide-zinc-800/70">
                                    {selectedOrder.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between py-2 text-xs"
                                        >
                                            <div>
                                                <p className="font-medium text-zinc-200">
                                                    {item.quantity} ×{' '}
                                                    {item.variant_snapshot
                                                        ?.product_name ??
                                                        'Producto'}
                                                </p>
                                                <p className="font-mono text-[10px] text-zinc-500">
                                                    {item.variant_snapshot?.sku}
                                                    {' · '}
                                                    {formatVariantAttributes(
                                                        item.variant_snapshot
                                                            ?.attributes,
                                                    )}
                                                </p>
                                            </div>
                                            <span className="font-mono font-semibold text-zinc-100">
                                                {formatMoney(
                                                    item.line_total,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                <div className="mt-2 space-y-1 border-t border-zinc-800 pt-2.5 text-xs">
                                    <div className="flex justify-between text-zinc-400">
                                        <span>Subtotal</span>
                                        <span className="font-mono">
                                            {formatMoney(
                                                selectedOrder.subtotal,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                    {Number(selectedOrder.auto_discount_total) >
                                        0 && (
                                        <div className="flex justify-between text-emerald-400">
                                            <span>Descuento automático</span>
                                            <span className="font-mono">
                                                -
                                                {formatMoney(
                                                    selectedOrder.auto_discount_total,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {Number(
                                        selectedOrder.coupon_discount_total,
                                    ) > 0 && (
                                        <div className="flex justify-between text-emerald-400">
                                            <span>Cupón aplicado</span>
                                            <span className="font-mono">
                                                -
                                                {formatMoney(
                                                    selectedOrder.coupon_discount_total,
                                                    currencyCode,
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-zinc-400">
                                        <span>Envío</span>
                                        <span className="font-mono">
                                            {formatMoney(
                                                selectedOrder.shipping_fee,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between border-t border-zinc-800 pt-1.5 font-semibold text-zinc-50">
                                        <span>Total Neto</span>
                                        <span className="font-mono text-sm text-emerald-400">
                                            {formatMoney(
                                                selectedOrder.total_net,
                                                currencyCode,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Comisión de Afiliado y Recompensas Cruzadas */}
                            {selectedOrder.commission && (
                                <div className="flex items-center justify-between rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3.5 py-2.5 text-xs">
                                    <div>
                                        <p className="font-medium text-indigo-200">
                                            Comisión Afiliado:{' '}
                                            {selectedOrder.commission.affiliate
                                                ?.name ?? 'Referido'}
                                        </p>
                                        <p className="font-mono text-[11px] text-indigo-300">
                                            Monto:{' '}
                                            {formatMoney(
                                                selectedOrder.commission.amount,
                                                currencyCode,
                                            )}
                                        </p>
                                    </div>
                                    <span
                                        className={`rounded-full border px-2 py-0.5 text-[10px] font-medium ${commissionStatusMeta(selectedOrder.commission.status).badgeClass}`}
                                    >
                                        {
                                            commissionStatusMeta(
                                                selectedOrder.commission.status,
                                            ).label
                                        }
                                    </span>
                                </div>
                            )}

                            {selectedOrder.outbound_rewards &&
                                selectedOrder.outbound_rewards.length > 0 && (
                                    <div className="space-y-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs">
                                        <p className="flex items-center gap-1.5 font-semibold text-emerald-300">
                                            <Gift className="size-3.5" />
                                            Recompensa Aliada Emitida
                                        </p>
                                        {selectedOrder.outbound_rewards.map(
                                            (rw) => (
                                                <div
                                                    key={rw.id}
                                                    className="flex items-center justify-between rounded border border-emerald-500/20 bg-zinc-950/70 px-2.5 py-1.5"
                                                >
                                                    <div>
                                                        <p className="font-mono font-bold text-zinc-100">
                                                            {rw.code}
                                                        </p>
                                                        <p className="text-[11px] text-zinc-400">
                                                            {
                                                                rw.partner_store
                                                                    ?.name
                                                            }
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={`rounded-full border px-2 py-0.5 text-[10px] ${voucherStatusMeta(rw.status).badgeClass}`}
                                                    >
                                                        {
                                                            voucherStatusMeta(
                                                                rw.status,
                                                            ).label
                                                        }
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}

                            {/* Acciones de Transición (Solo PENDIENTE_PAGO) */}
                            {selectedOrder.status === 'PENDIENTE_PAGO' && (
                                <div className="space-y-3 border-t border-zinc-800 pt-4">
                                    <div>
                                        <Label className="text-xs text-zinc-300">
                                            Nota de Pago / Referencia Bancaria
                                            (al completar)
                                        </Label>
                                        <Input
                                            value={paymentNotes}
                                            onChange={(e) =>
                                                setPaymentNotes(e.target.value)
                                            }
                                            placeholder="Ej. Transferencia BAC #884920 verificada"
                                            className="mt-1 h-8 border-zinc-800 bg-zinc-950 text-xs"
                                        />
                                    </div>

                                    <div>
                                        <Label className="text-xs text-zinc-400">
                                            Motivo de Cancelación (si el cliente
                                            desiste)
                                        </Label>
                                        <Input
                                            value={cancellationReason}
                                            onChange={(e) =>
                                                setCancellationReason(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Ej. Sin respuesta tras 24h en WhatsApp"
                                            className="mt-1 h-8 border-zinc-800 bg-zinc-950 text-xs"
                                        />
                                    </div>

                                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <Button
                                            type="button"
                                            disabled={isTransitioning}
                                            onClick={() =>
                                                void handleTransition(
                                                    'COMPLETADA',
                                                )
                                            }
                                            className="h-9 gap-1.5 bg-emerald-500 text-xs font-bold text-zinc-950 hover:bg-emerald-400"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            <span>
                                                Confirmar Pago (Completar)
                                            </span>
                                        </Button>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={isTransitioning}
                                            onClick={() =>
                                                void handleTransition(
                                                    'CANCELADA',
                                                )
                                            }
                                            className="h-9 gap-1.5 border-rose-500/40 bg-rose-500/10 text-xs text-rose-300 hover:bg-rose-500/20"
                                        >
                                            <XCircle className="size-4" />
                                            <span>
                                                Cancelar y Revertir Stock
                                            </span>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function TenantAdminOrdersPage(props: PortalShellPageProps) {
    return (
        <TenantAdminLayout
            tenantSlug={props.tenantSlug}
            tenantName={props.tenantName}
            currencyCode={props.currencyCode}
            countryCode={props.countryCode}
            urlMode={props.urlMode}
            activeSection="orders"
            title="Centro de Órdenes WhatsApp"
            subtitle="Confirmación de cobros, reversión de inventario y emisión de vouchers aliados"
        >
            <OrdersHubContent />
        </TenantAdminLayout>
    );
}
