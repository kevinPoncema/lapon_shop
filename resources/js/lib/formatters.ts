import {
    type CommissionStatus,
    type CouponRejectionCode,
    type OrderStatus,
    type VoucherStatus,
} from '@/types/commerce';

export function toNumber(value: string | number | null | undefined): number {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
}

export function formatMoney(
    amount: string | number | null | undefined,
    currencyCode = 'HNL',
): string {
    const numeric = toNumber(amount);
    const normalizedCurrency = (currencyCode || 'HNL').toUpperCase();

    try {
        return new Intl.NumberFormat('es-HN', {
            style: 'currency',
            currency: normalizedCurrency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(numeric);
    } catch {
        return `${normalizedCurrency} ${numeric.toFixed(2)}`;
    }
}

export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return new Intl.DateTimeFormat('es-HN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export function stringifyMetadataValue(val: unknown): string {
    if (val === null || val === undefined) {
        return '';
    }
    if (typeof val === 'string') {
        return val;
    }
    if (typeof val === 'number') {
        return String(val);
    }
    if (typeof val === 'boolean') {
        return val ? 'Sí' : 'No';
    }
    if (Array.isArray(val)) {
        return val
            .map((item) =>
                typeof item === 'string' || typeof item === 'number'
                    ? String(item)
                    : '',
            )
            .filter(Boolean)
            .join(', ');
    }
    return JSON.stringify(val);
}

export function formatVariantAttributes(
    metadata: Record<string, unknown> | null | undefined,
): string {
    if (!metadata) {
        return '';
    }

    const parts = Object.entries(metadata)
        .map(([key, val]) => [key, stringifyMetadataValue(val).trim()] as const)
        .filter(([, text]) => text !== '')
        .map(([key, text]) => `${key}: ${text}`);

    return parts.join(' · ');
}

export function orderStatusMeta(status: OrderStatus): {
    label: string;
    badgeClass: string;
} {
    switch (status) {
        case 'PENDIENTE_PAGO':
            return {
                label: 'Pendiente Pago',
                badgeClass:
                    'border-amber-500/30 bg-amber-500/10 text-amber-300',
            };
        case 'COMPLETADA':
            return {
                label: 'Completada',
                badgeClass:
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            };
        case 'CANCELADA':
            return {
                label: 'Cancelada',
                badgeClass: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
            };
    }
}

export function commissionStatusMeta(status: CommissionStatus): {
    label: string;
    badgeClass: string;
} {
    switch (status) {
        case 'RETENIDA':
            return {
                label: 'Retenida',
                badgeClass:
                    'border-amber-500/30 bg-amber-500/10 text-amber-300',
            };
        case 'APROBADA':
            return {
                label: 'Aprobada (Liquidable)',
                badgeClass:
                    'border-indigo-500/30 bg-indigo-500/10 text-indigo-300',
            };
        case 'PAGADA':
            return {
                label: 'Pagada',
                badgeClass:
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
            };
        case 'ANULADA':
            return {
                label: 'Anulada',
                badgeClass: 'border-zinc-600/40 bg-zinc-800/60 text-zinc-400',
            };
    }
}

export function voucherStatusMeta(status: VoucherStatus): {
    label: string;
    badgeClass: string;
} {
    switch (status) {
        case 'EMITIDO':
            return {
                label: 'Activo / Canjeable',
                badgeClass:
                    'border-emerald-500/40 bg-emerald-500/15 text-emerald-300',
            };
        case 'CANJEADO':
            return {
                label: 'Ya Canjeado',
                badgeClass:
                    'border-indigo-500/40 bg-indigo-500/15 text-indigo-300',
            };
        case 'EXPIRADO':
            return {
                label: 'Expirado',
                badgeClass: 'border-rose-500/40 bg-rose-500/15 text-rose-300',
            };
    }
}

export function translateCouponRejection(
    code?: CouponRejectionCode,
    fallbackMessage?: string | null,
): string {
    if (fallbackMessage) {
        return fallbackMessage;
    }

    switch (code) {
        case 'COUPON_NOT_FOUND':
            return 'El cupón ingresado no existe en esta tienda.';
        case 'COUPON_INACTIVE':
            return 'Este cupón no se encuentra activo.';
        case 'COUPON_EXPIRED':
            return 'La vigencia de este cupón ha expirado.';
        case 'COUPON_EXHAUSTED':
            return 'Este cupón alcanzó su límite máximo de usos.';
        case 'CONDITIONS_NOT_MET':
            return 'Tu carrito aún no cumple el mínimo requerido para este cupón.';
        case 'NON_STACKABLE_LOWER_BENEFIT':
            return 'Ya tienes aplicada una promoción automática con mayor descuento.';
        default:
            return 'No fue posible aplicar este cupón.';
    }
}
