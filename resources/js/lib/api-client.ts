import adminAffiliates from '@/routes/api/v1/admin/affiliates';
import adminCommissions from '@/routes/api/v1/admin/affiliates/commissions';
import adminAuth from '@/routes/api/v1/admin/auth';
import adminCategories from '@/routes/api/v1/admin/categories';
import adminConfig from '@/routes/api/v1/admin/config';
import adminBranding from '@/routes/api/v1/admin/config/branding';
import adminCatalogSchema from '@/routes/api/v1/admin/config/catalog_schema';
import adminContactInfo from '@/routes/api/v1/admin/config/contact_info';
import adminOutboundRewards from '@/routes/api/v1/admin/config/outbound_rewards';
import adminShippingZones from '@/routes/api/v1/admin/config/shipping_zones';
import adminCoupons from '@/routes/api/v1/admin/coupons';
import adminOrders from '@/routes/api/v1/admin/orders';
import adminOrderStatus from '@/routes/api/v1/admin/orders/status';
import adminPartnerStores from '@/routes/api/v1/admin/partner_stores';
import adminProducts from '@/routes/api/v1/admin/products';
import adminPromotions from '@/routes/api/v1/admin/promotions';
import adminVariantStatus from '@/routes/api/v1/admin/variants/status';
import adminVariantStock from '@/routes/api/v1/admin/variants/stock';
import partnerAuth from '@/routes/api/v1/partner_portal/auth';
import partnerVouchers from '@/routes/api/v1/partner_portal/vouchers';
import storefront from '@/routes/api/v1/storefront';
import storefrontCart from '@/routes/api/v1/storefront/cart';
import storefrontCategories from '@/routes/api/v1/storefront/categories';
import storefrontProducts from '@/routes/api/v1/storefront/products';
import {
    type AdminLoginResponse,
    type AdminMeResponse,
    type Affiliate,
    type AffiliateCommissionsLedgerResponse,
    type CartQuotePayload,
    type CartQuoteResponse,
    type CategoryNode,
    type CommissionStatus,
    type Coupon,
    type DiscountType,
    type FilterConfigDefinition,
    type FormattedProductDetail,
    type LaravelLengthAwarePaginator,
    type MetadataAttributeDefinition,
    type OrderModel,
    type OrderStatus,
    type OrderTargetStatus,
    type OrderTransitionResult,
    type OutboundRewardRules,
    type PaginationMeta,
    type PartnerLoginResponse,
    type PartnerMeResponse,
    type PartnerStoreAdmin,
    type Promotion,
    type PromotionType,
    type ShippingZone,
    type StockAdjustmentMode,
    type StorefrontBootstrapResponse,
    type StorefrontCheckoutPayload,
    type StorefrontCheckoutResponse,
    type StorefrontProductListResponse,
    type StorefrontProductQuery,
    type TenantBranding,
    type TenantConfigResponse,
    type TenantContactInfo,
    type UpsertProductPayload,
    type VoucherInspectionResponse,
    type VoucherRedemptionResponse,
} from '@/types/commerce';
import { type QueryParams, type RouteDefinition } from '@/wayfinder';

export type AuthScope = 'none' | 'admin' | 'partner';

const ADMIN_TOKEN_PREFIX = 'ch_admin_token:';
const PARTNER_TOKEN_PREFIX = 'ch_partner_token:';

export const tokenStorage = {
    getAdminToken(tenantSlug: string): string | null {
        if (typeof window === 'undefined') {
            return null;
        }
        return window.localStorage.getItem(
            `${ADMIN_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
        );
    },

    setAdminToken(tenantSlug: string, token: string): void {
        if (typeof window === 'undefined') {
            return;
        }
        window.localStorage.setItem(
            `${ADMIN_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
            token,
        );
    },

    clearAdminToken(tenantSlug: string): void {
        if (typeof window === 'undefined') {
            return;
        }
        window.localStorage.removeItem(
            `${ADMIN_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
        );
    },

    getPartnerToken(tenantSlug: string): string | null {
        if (typeof window === 'undefined') {
            return null;
        }
        return window.localStorage.getItem(
            `${PARTNER_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
        );
    },

    setPartnerToken(tenantSlug: string, token: string): void {
        if (typeof window === 'undefined') {
            return;
        }
        window.localStorage.setItem(
            `${PARTNER_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
            token,
        );
    },

    clearPartnerToken(tenantSlug: string): void {
        if (typeof window === 'undefined') {
            return;
        }
        window.localStorage.removeItem(
            `${PARTNER_TOKEN_PREFIX}${tenantSlug.toLowerCase()}`,
        );
    },
};

export class ApiClientError extends Error {
    public readonly status: number;
    public readonly code: string | null;
    public readonly validationErrors: Record<string, string[]>;
    public readonly redeemedAt: string | null;
    public readonly expiresAt: string | null;

    constructor(params: {
        message: string;
        status: number;
        code?: string | null;
        validationErrors?: Record<string, string[]>;
        redeemedAt?: string | null;
        expiresAt?: string | null;
    }) {
        super(params.message);
        this.name = 'ApiClientError';
        this.status = params.status;
        this.code = params.code ?? null;
        this.validationErrors = params.validationErrors ?? {};
        this.redeemedAt = params.redeemedAt ?? null;
        this.expiresAt = params.expiresAt ?? null;
    }

    public firstFieldError(): string | null {
        const entries = Object.values(this.validationErrors);
        for (const messages of entries) {
            if (messages.length > 0 && messages[0]) {
                return messages[0];
            }
        }
        return null;
    }
}

interface RequestOptions {
    tenantSlug: string;
    authScope?: AuthScope;
    body?: unknown;
    headers?: Record<string, string>;
    signal?: AbortSignal;
}

export async function apiRequest<TResponse>(
    route: RouteDefinition<'get' | 'post' | 'put' | 'patch' | 'delete'>,
    options: RequestOptions,
): Promise<TResponse> {
    const {
        tenantSlug,
        authScope = 'none',
        body,
        headers: extraHeaders,
        signal,
    } = options;

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Tenant-Slug': tenantSlug.toLowerCase().trim(),
        ...extraHeaders,
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    if (authScope === 'admin') {
        const token = tokenStorage.getAdminToken(tenantSlug);
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
    } else if (authScope === 'partner') {
        const token = tokenStorage.getPartnerToken(tenantSlug);
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
    }

    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
        signal,
    });

    const rawText = await response.text();
    let parsed: unknown = null;

    if (rawText.trim().length > 0) {
        try {
            parsed = JSON.parse(rawText);
        } catch {
            parsed = null;
        }
    }

    if (!response.ok) {
        const obj =
            parsed !== null && typeof parsed === 'object'
                ? (parsed as Record<string, unknown>)
                : {};

        const domainError =
            obj.error !== null && typeof obj.error === 'object'
                ? (obj.error as Record<string, unknown>)
                : null;

        const validationErrors =
            obj.errors !== null && typeof obj.errors === 'object'
                ? (obj.errors as Record<string, string[]>)
                : {};

        const code =
            typeof domainError?.code === 'string' ? domainError.code : null;

        const message =
            (typeof domainError?.message === 'string' && domainError.message) ||
            (typeof obj.message === 'string' && obj.message) ||
            `Error HTTP ${response.status}`;

        throw new ApiClientError({
            message,
            status: response.status,
            code,
            validationErrors,
            redeemedAt:
                typeof domainError?.redeemed_at === 'string'
                    ? domainError.redeemed_at
                    : null,
            expiresAt:
                typeof domainError?.expires_at === 'string'
                    ? domainError.expires_at
                    : null,
        });
    }

    return parsed as TResponse;
}

export const commerceApi = {
    storefront: {
        getConfig: (tenantSlug: string, signal?: AbortSignal) =>
            apiRequest<StorefrontBootstrapResponse>(storefront.config(), {
                tenantSlug,
                authScope: 'none',
                signal,
            }),

        getCategories: (tenantSlug: string, signal?: AbortSignal) =>
            apiRequest<{ data: CategoryNode[] }>(storefrontCategories.index(), {
                tenantSlug,
                authScope: 'none',
                signal,
            }),

        getProducts: (
            tenantSlug: string,
            query: StorefrontProductQuery = {},
            signal?: AbortSignal,
        ) =>
            apiRequest<StorefrontProductListResponse>(
                storefrontProducts.index({
                    query: query as unknown as QueryParams,
                }),
                {
                    tenantSlug,
                    authScope: 'none',
                    signal,
                },
            ),

        getProduct: (
            tenantSlug: string,
            productSlug: string,
            signal?: AbortSignal,
        ) =>
            apiRequest<{ data: FormattedProductDetail }>(
                storefrontProducts.show(productSlug),
                {
                    tenantSlug,
                    authScope: 'none',
                    signal,
                },
            ),

        quoteCart: (
            tenantSlug: string,
            payload: CartQuotePayload,
            signal?: AbortSignal,
        ) =>
            apiRequest<CartQuoteResponse>(storefrontCart.quote(), {
                tenantSlug,
                authScope: 'none',
                body: payload,
                signal,
            }),

        checkout: (
            tenantSlug: string,
            payload: StorefrontCheckoutPayload,
            idempotencyKey: string,
        ) =>
            apiRequest<StorefrontCheckoutResponse>(storefront.checkout(), {
                tenantSlug,
                authScope: 'none',
                body: payload,
                headers: {
                    'Idempotency-Key': idempotencyKey,
                },
            }),
    },

    admin: {
        login: (
            tenantSlug: string,
            payload: { email: string; password: string; device_name?: string },
        ) =>
            apiRequest<AdminLoginResponse>(adminAuth.login(), {
                tenantSlug,
                authScope: 'none',
                body: payload,
            }),

        me: (tenantSlug: string, signal?: AbortSignal) =>
            apiRequest<AdminMeResponse>(adminAuth.me(), {
                tenantSlug,
                authScope: 'admin',
                signal,
            }),

        logout: (tenantSlug: string) =>
            apiRequest<{ message: string }>(adminAuth.logout(), {
                tenantSlug,
                authScope: 'admin',
            }),

        logoutAll: (tenantSlug: string) =>
            apiRequest<{ message: string }>(adminAuth.logout_all(), {
                tenantSlug,
                authScope: 'admin',
            }),

        getConfig: (tenantSlug: string, signal?: AbortSignal) =>
            apiRequest<TenantConfigResponse>(adminConfig.show(), {
                tenantSlug,
                authScope: 'admin',
                signal,
            }),

        updateCatalogSchema: (
            tenantSlug: string,
            payload: {
                metadata_schema?: MetadataAttributeDefinition[];
                filter_config?: FilterConfigDefinition[];
            },
        ) =>
            apiRequest<TenantConfigResponse>(adminCatalogSchema.update(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        updateBranding: (tenantSlug: string, payload: TenantBranding) =>
            apiRequest<TenantConfigResponse>(adminBranding.update(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        updateShippingZones: (
            tenantSlug: string,
            shippingZones: ShippingZone[],
        ) =>
            apiRequest<TenantConfigResponse>(adminShippingZones.update(), {
                tenantSlug,
                authScope: 'admin',
                body: { shipping_zones: shippingZones },
            }),

        updateContactInfo: (tenantSlug: string, payload: TenantContactInfo) =>
            apiRequest<TenantConfigResponse>(adminContactInfo.update(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        updateOutboundRewards: (
            tenantSlug: string,
            payload: OutboundRewardRules,
        ) =>
            apiRequest<TenantConfigResponse>(adminOutboundRewards.update(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getCategories: (tenantSlug: string) =>
            apiRequest<{ data: CategoryNode[] }>(adminCategories.index(), {
                tenantSlug,
                authScope: 'admin',
            }),

        createCategory: (
            tenantSlug: string,
            payload: {
                name: string;
                slug?: string | null;
                parent_id?: string | null;
                is_active?: boolean;
            },
        ) =>
            apiRequest<{ data: CategoryNode }>(adminCategories.store(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getProducts: (
            tenantSlug: string,
            query: {
                q?: string;
                category_id?: string;
                is_active?: boolean;
                in_stock?: boolean;
                attr?: Record<string, string>;
                page?: number;
                per_page?: number;
            } = {},
        ) =>
            apiRequest<{
                data: FormattedProductDetail[];
                meta: PaginationMeta;
            }>(
                adminProducts.index({ query: query as unknown as QueryParams }),
                {
                    tenantSlug,
                    authScope: 'admin',
                },
            ),

        getProduct: (tenantSlug: string, id: string) =>
            apiRequest<{ data: FormattedProductDetail }>(
                adminProducts.show(id),
                {
                    tenantSlug,
                    authScope: 'admin',
                },
            ),

        createProduct: (tenantSlug: string, payload: UpsertProductPayload) =>
            apiRequest<{ data: FormattedProductDetail }>(
                adminProducts.store(),
                {
                    tenantSlug,
                    authScope: 'admin',
                    body: payload,
                },
            ),

        updateProduct: (
            tenantSlug: string,
            id: string,
            payload: Partial<UpsertProductPayload>,
        ) =>
            apiRequest<{ data: FormattedProductDetail }>(
                adminProducts.update(id),
                {
                    tenantSlug,
                    authScope: 'admin',
                    body: payload,
                },
            ),

        adjustVariantStock: (
            tenantSlug: string,
            variantId: string,
            payload: {
                mode: StockAdjustmentMode;
                quantity: number;
                reason?: string;
            },
        ) =>
            apiRequest<{
                data: {
                    variant_id: string;
                    sku: string;
                    previous_stock: number;
                    current_stock: number;
                    adjusted_by: number;
                };
            }>(adminVariantStock.adjust(variantId), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        updateVariantStatus: (
            tenantSlug: string,
            variantId: string,
            isActive: boolean,
        ) =>
            apiRequest<{
                data: {
                    id: string;
                    sku: string;
                    is_active: boolean;
                    is_default: boolean;
                };
            }>(adminVariantStatus.update(variantId), {
                tenantSlug,
                authScope: 'admin',
                body: { is_active: isActive },
            }),

        getPromotions: (tenantSlug: string) =>
            apiRequest<{ data: Promotion[] }>(adminPromotions.index(), {
                tenantSlug,
                authScope: 'admin',
            }),

        createPromotion: (
            tenantSlug: string,
            payload: {
                name: string;
                type: PromotionType;
                discount_type: DiscountType;
                discount_value: number;
                conditions?: Promotion['conditions'] | null;
                allow_stacking?: boolean;
                starts_at: string;
                ends_at?: string | null;
                is_active?: boolean;
            },
        ) =>
            apiRequest<{ data: Promotion }>(adminPromotions.store(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        updatePromotion: (
            tenantSlug: string,
            id: string,
            payload: Partial<{
                name: string;
                type: PromotionType;
                discount_type: DiscountType;
                discount_value: number;
                conditions?: Promotion['conditions'] | null;
                allow_stacking: boolean;
                starts_at: string;
                ends_at?: string | null;
                is_active: boolean;
            }>,
        ) =>
            apiRequest<{ data: Promotion }>(adminPromotions.update(id), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getCoupons: (tenantSlug: string) =>
            apiRequest<{ data: Coupon[] }>(adminCoupons.index(), {
                tenantSlug,
                authScope: 'admin',
            }),

        createCoupon: (
            tenantSlug: string,
            payload: {
                promotion_id: string;
                affiliate_id?: string | null;
                code: string;
                max_uses?: number | null;
                starts_at: string;
                ends_at?: string | null;
                is_active?: boolean;
            },
        ) =>
            apiRequest<{ data: Coupon }>(adminCoupons.store(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getAffiliates: (tenantSlug: string) =>
            apiRequest<{ data: Affiliate[] }>(adminAffiliates.index(), {
                tenantSlug,
                authScope: 'admin',
            }),

        createAffiliate: (
            tenantSlug: string,
            payload: {
                name: string;
                email: string;
                phone: string;
                commission_type: 'PERCENTAGE' | 'FIXED_AMOUNT';
                commission_value: number;
                payment_info?: Record<string, unknown> | null;
                is_active?: boolean;
            },
        ) =>
            apiRequest<{ data: Affiliate }>(adminAffiliates.store(), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getAffiliateCommissions: (
            tenantSlug: string,
            affiliateId: string,
            query: {
                status?: CommissionStatus;
                page?: number;
                per_page?: number;
            } = {},
        ) =>
            apiRequest<AffiliateCommissionsLedgerResponse>(
                adminCommissions.index(affiliateId, {
                    query: query as unknown as QueryParams,
                }),
                {
                    tenantSlug,
                    authScope: 'admin',
                },
            ),

        settleAffiliateCommissions: (
            tenantSlug: string,
            affiliateId: string,
            payload: {
                commission_ids: string[];
                payment_reference: string;
                payment_receipt_url?: string | null;
            },
        ) =>
            apiRequest<{
                settled_count: number;
                total_paid: number;
                status: 'PAGADA';
                payment_reference: string;
                paid_at: string;
            }>(adminCommissions.settle(affiliateId), {
                tenantSlug,
                authScope: 'admin',
                body: payload,
            }),

        getPartnerStores: (tenantSlug: string) =>
            apiRequest<{ data: PartnerStoreAdmin[] }>(
                adminPartnerStores.index(),
                {
                    tenantSlug,
                    authScope: 'admin',
                },
            ),

        createPartnerStore: (
            tenantSlug: string,
            payload: {
                name: string;
                portal_email: string;
                portal_password: string;
                benefit_title: string;
                terms_conditions: string;
                contact_info?: Record<string, unknown> | null;
                is_active?: boolean;
            },
        ) =>
            apiRequest<{ data: PartnerStoreAdmin }>(
                adminPartnerStores.store(),
                {
                    tenantSlug,
                    authScope: 'admin',
                    body: payload,
                },
            ),

        updatePartnerStore: (
            tenantSlug: string,
            id: string,
            payload: Partial<{
                name: string;
                portal_email: string;
                portal_password: string | null;
                benefit_title: string;
                terms_conditions: string;
                contact_info: Record<string, unknown> | null;
                is_active: boolean;
            }>,
        ) =>
            apiRequest<{ data: PartnerStoreAdmin }>(
                adminPartnerStores.update(id),
                {
                    tenantSlug,
                    authScope: 'admin',
                    body: payload,
                },
            ),

        getOrders: (
            tenantSlug: string,
            query: {
                status?: OrderStatus;
                date_from?: string;
                date_to?: string;
                q?: string;
                page?: number;
                per_page?: number;
            } = {},
        ) =>
            apiRequest<LaravelLengthAwarePaginator<OrderModel>>(
                adminOrders.index({ query: query as unknown as QueryParams }),
                {
                    tenantSlug,
                    authScope: 'admin',
                },
            ),

        getOrder: (tenantSlug: string, id: string) =>
            apiRequest<{ data: OrderModel }>(adminOrders.show(id), {
                tenantSlug,
                authScope: 'admin',
            }),

        updateOrderStatus: (
            tenantSlug: string,
            id: string,
            payload: {
                status: OrderTargetStatus;
                payment_notes?: string | null;
                cancellation_reason?: string | null;
            },
        ) =>
            apiRequest<OrderTransitionResult & { data: OrderTransitionResult }>(
                adminOrderStatus.update(id),
                {
                    tenantSlug,
                    authScope: 'admin',
                    body: payload,
                },
            ),
    },

    partner: {
        login: (
            tenantSlug: string,
            payload: { email: string; password: string; device_name?: string },
        ) =>
            apiRequest<PartnerLoginResponse>(partnerAuth.login(), {
                tenantSlug,
                authScope: 'none',
                body: payload,
            }),

        me: (tenantSlug: string, signal?: AbortSignal) =>
            apiRequest<PartnerMeResponse>(partnerAuth.me(), {
                tenantSlug,
                authScope: 'partner',
                signal,
            }),

        logout: (tenantSlug: string) =>
            apiRequest<{ message: string }>(partnerAuth.logout(), {
                tenantSlug,
                authScope: 'partner',
            }),

        inspectVoucher: (tenantSlug: string, code: string) =>
            apiRequest<VoucherInspectionResponse>(
                partnerVouchers.show(code.trim().toUpperCase()),
                {
                    tenantSlug,
                    authScope: 'partner',
                },
            ),

        redeemVoucher: (tenantSlug: string, code: string) =>
            apiRequest<VoucherRedemptionResponse>(
                partnerVouchers.redeem(code.trim().toUpperCase()),
                {
                    tenantSlug,
                    authScope: 'partner',
                },
            ),
    },
};
