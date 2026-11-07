import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import { useTenant } from '@/contexts/tenant-context';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import { type CartQuoteResponse } from '@/types/commerce';

export interface LocalCartItem {
    variantId: string;
    quantity: number;
    productName: string;
    productSlug: string;
    sku: string;
    unitPrice: number;
    image?: string | null;
    variantLabel?: string;
}

interface StoredCartState {
    items: LocalCartItem[];
    couponCode: string;
    shippingZoneCode: string;
}

interface CartContextValue {
    items: LocalCartItem[];
    totalQuantity: number;
    couponCode: string;
    shippingZoneCode: string;
    quote: CartQuoteResponse | null;
    isQuoting: boolean;
    quoteError: string | null;
    isDrawerOpen: boolean;
    setDrawerOpen: (open: boolean) => void;
    addItem: (item: Omit<LocalCartItem, 'quantity'>, quantity?: number) => void;
    updateQuantity: (variantId: string, quantity: number) => void;
    removeItem: (variantId: string) => void;
    clearCart: () => void;
    setCouponCode: (code: string) => void;
    setShippingZoneCode: (zoneCode: string) => void;
    getCheckoutIdempotencyKey: () => string;
    resetCheckoutIdempotencyKey: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);

const CART_STORAGE_PREFIX = 'ch_cart:';

function generateUuidV4(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const rand = (Math.random() * 16) | 0;
        const val = char === 'x' ? rand : (rand & 0x3) | 0x8;
        return val.toString(16);
    });
}

function readStoredCart(tenantSlug: string): StoredCartState {
    if (typeof window === 'undefined') {
        return { items: [], couponCode: '', shippingZoneCode: '' };
    }

    try {
        const raw = window.localStorage.getItem(
            `${CART_STORAGE_PREFIX}${tenantSlug.toLowerCase()}`,
        );
        if (!raw) {
            return { items: [], couponCode: '', shippingZoneCode: '' };
        }
        const parsed = JSON.parse(raw) as Partial<StoredCartState>;
        return {
            items: Array.isArray(parsed.items) ? parsed.items : [],
            couponCode:
                typeof parsed.couponCode === 'string' ? parsed.couponCode : '',
            shippingZoneCode:
                typeof parsed.shippingZoneCode === 'string'
                    ? parsed.shippingZoneCode
                    : '',
        };
    } catch {
        return { items: [], couponCode: '', shippingZoneCode: '' };
    }
}

export function CartProvider({ children }: { children: ReactNode }) {
    const { tenantSlug, bootstrap } = useTenant();

    const [state, setState] = useState<StoredCartState>(() =>
        readStoredCart(tenantSlug),
    );
    const [quote, setQuote] = useState<CartQuoteResponse | null>(null);
    const [isQuoting, setIsQuoting] = useState<boolean>(false);
    const [quoteError, setQuoteError] = useState<string | null>(null);
    const [isDrawerOpen, setDrawerOpen] = useState<boolean>(false);

    const idempotencyRef = useRef<{ fingerprint: string; key: string } | null>(
        null,
    );

    // Reload isolated cart if tenantSlug changes
    useEffect(() => {
        setState(readStoredCart(tenantSlug));
        setQuote(null);
        setQuoteError(null);
        idempotencyRef.current = null;
    }, [tenantSlug]);

    // Capture ?coupon= or ?ref= affiliate coupon parameter from URL
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const urlCoupon = (
            params.get('coupon') ??
            params.get('ref') ??
            ''
        ).trim();

        if (urlCoupon.length > 0) {
            const normalized = urlCoupon.toUpperCase();
            setState((prev) => {
                if (prev.couponCode === normalized) {
                    return prev;
                }
                toast.success(
                    `Código promocional ${normalized} detectado y vinculado a tu bolsa.`,
                );
                return { ...prev, couponCode: normalized };
            });
        }
    }, [tenantSlug]);

    // Default shipping zone to first tenant shipping zone when available
    useEffect(() => {
        const zones = bootstrap?.shipping_zones ?? [];
        if (zones.length === 0) {
            return;
        }

        setState((prev) => {
            const exists = zones.some((z) => z.code === prev.shippingZoneCode);
            if (exists) {
                return prev;
            }
            const firstCode = zones[0]?.code ?? '';
            if (!firstCode || prev.shippingZoneCode === firstCode) {
                return prev;
            }
            return { ...prev, shippingZoneCode: firstCode };
        });
    }, [bootstrap?.shipping_zones]);

    // Persist cart per tenant in localStorage
    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }
        try {
            window.localStorage.setItem(
                `${CART_STORAGE_PREFIX}${tenantSlug.toLowerCase()}`,
                JSON.stringify(state),
            );
        } catch {
            // Ignore storage quota errors
        }
    }, [tenantSlug, state]);

    // Live debounced cart quote against POST /api/v1/storefront/cart/quote
    useEffect(() => {
        if (state.items.length === 0) {
            setQuote(null);
            setQuoteError(null);
            setIsQuoting(false);
            return;
        }

        const controller = new AbortController();
        setIsQuoting(true);

        const timer = window.setTimeout(() => {
            void (async () => {
                try {
                    const response = await commerceApi.storefront.quoteCart(
                        tenantSlug,
                        {
                            items: state.items.map((item) => ({
                                variant_id: item.variantId,
                                quantity: item.quantity,
                            })),
                            coupon_code:
                                state.couponCode.trim().length > 0
                                    ? state.couponCode.trim().toUpperCase()
                                    : null,
                            shipping_zone_code:
                                state.shippingZoneCode.trim().length > 0
                                    ? state.shippingZoneCode.trim()
                                    : null,
                        },
                        controller.signal,
                    );
                    setQuote(response);
                    setQuoteError(null);
                } catch (err) {
                    if (
                        err instanceof DOMException &&
                        err.name === 'AbortError'
                    ) {
                        return;
                    }
                    const msg =
                        err instanceof ApiClientError
                            ? (err.firstFieldError() ?? err.message)
                            : 'No fue posible actualizar la cotización del carrito.';
                    setQuoteError(msg);
                } finally {
                    if (!controller.signal.aborted) {
                        setIsQuoting(false);
                    }
                }
            })();
        }, 220);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [tenantSlug, state.items, state.couponCode, state.shippingZoneCode]);

    const addItem = useCallback(
        (item: Omit<LocalCartItem, 'quantity'>, quantity = 1) => {
            const safeQty = Math.max(1, Math.floor(quantity));
            setState((prev) => {
                const existingIndex = prev.items.findIndex(
                    (i) => i.variantId === item.variantId,
                );
                if (existingIndex >= 0) {
                    const nextItems = [...prev.items];
                    const current = nextItems[existingIndex];
                    if (current) {
                        nextItems[existingIndex] = {
                            ...current,
                            ...item,
                            quantity: current.quantity + safeQty,
                        };
                    }
                    return { ...prev, items: nextItems };
                }

                return {
                    ...prev,
                    items: [...prev.items, { ...item, quantity: safeQty }],
                };
            });
            setDrawerOpen(true);
        },
        [],
    );

    const updateQuantity = useCallback(
        (variantId: string, quantity: number) => {
            if (quantity <= 0) {
                setState((prev) => ({
                    ...prev,
                    items: prev.items.filter((i) => i.variantId !== variantId),
                }));
                return;
            }

            const safeQty = Math.floor(quantity);
            setState((prev) => ({
                ...prev,
                items: prev.items.map((i) =>
                    i.variantId === variantId ? { ...i, quantity: safeQty } : i,
                ),
            }));
        },
        [],
    );

    const removeItem = useCallback((variantId: string) => {
        setState((prev) => ({
            ...prev,
            items: prev.items.filter((i) => i.variantId !== variantId),
        }));
    }, []);

    const clearCart = useCallback(() => {
        setState((prev) => ({ ...prev, items: [], couponCode: '' }));
        setQuote(null);
        setQuoteError(null);
        idempotencyRef.current = null;
    }, []);

    const setCouponCode = useCallback((code: string) => {
        setState((prev) => ({
            ...prev,
            couponCode: code.trim().toUpperCase(),
        }));
    }, []);

    const setShippingZoneCode = useCallback((zoneCode: string) => {
        setState((prev) => ({
            ...prev,
            shippingZoneCode: zoneCode,
        }));
    }, []);

    const cartFingerprint = useMemo(
        () =>
            JSON.stringify({
                items: state.items.map((i) => [i.variantId, i.quantity]),
                coupon: state.couponCode,
                zone: state.shippingZoneCode,
            }),
        [state.items, state.couponCode, state.shippingZoneCode],
    );

    const getCheckoutIdempotencyKey = useCallback((): string => {
        if (
            idempotencyRef.current &&
            idempotencyRef.current.fingerprint === cartFingerprint
        ) {
            return idempotencyRef.current.key;
        }

        const nextKey = generateUuidV4();
        idempotencyRef.current = {
            fingerprint: cartFingerprint,
            key: nextKey,
        };
        return nextKey;
    }, [cartFingerprint]);

    const resetCheckoutIdempotencyKey = useCallback(() => {
        idempotencyRef.current = null;
    }, []);

    const totalQuantity = useMemo(
        () => state.items.reduce((acc, item) => acc + item.quantity, 0),
        [state.items],
    );

    const value = useMemo<CartContextValue>(
        () => ({
            items: state.items,
            totalQuantity,
            couponCode: state.couponCode,
            shippingZoneCode: state.shippingZoneCode,
            quote,
            isQuoting,
            quoteError,
            isDrawerOpen,
            setDrawerOpen,
            addItem,
            updateQuantity,
            removeItem,
            clearCart,
            setCouponCode,
            setShippingZoneCode,
            getCheckoutIdempotencyKey,
            resetCheckoutIdempotencyKey,
        }),
        [
            state.items,
            totalQuantity,
            state.couponCode,
            state.shippingZoneCode,
            quote,
            isQuoting,
            quoteError,
            isDrawerOpen,
            addItem,
            updateQuantity,
            removeItem,
            clearCart,
            setCouponCode,
            setShippingZoneCode,
            getCheckoutIdempotencyKey,
            resetCheckoutIdempotencyKey,
        ],
    );

    return (
        <CartContext.Provider value={value}>{children}</CartContext.Provider>
    );
}

export function useCart(): CartContextValue {
    const context = useContext(CartContext);
    if (!context) {
        throw new Error('useCart debe usarse dentro de un CartProvider.');
    }
    return context;
}
