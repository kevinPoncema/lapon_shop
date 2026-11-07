import { type PortalUrlMode } from '@/lib/portal-paths';

export type UserRole = 'OWNER' | 'STAFF';
export type AdminTokenAbility = 'tenant:admin' | 'tenant:staff';
export type PartnerTokenAbility = 'partner-portal';

export type MetadataDataType =
    | 'text'
    | 'number'
    | 'boolean'
    | 'select'
    | 'multiselect'
    | 'date';

export type FilterConfigType = 'system' | 'metadata';
export type StorefrontProductSort = 'price_asc' | 'price_desc' | 'newest';
export type StockAdjustmentMode = 'set' | 'adjust';

export const RESERVED_METADATA_KEYWORDS = [
    'id',
    'tenant_id',
    'name',
    'slug',
    'description',
    'category',
    'category_id',
    'sku',
    'price',
    'base_price',
    'stock',
    'status',
    'is_active',
    'created_at',
    'updated_at',
    'deleted_at',
    'metadata',
    'images',
] as const;

export type PromotionType = 'AUTOMATIC' | 'COUPON';
export type DiscountType = 'PERCENTAGE' | 'FIXED_AMOUNT' | 'FREE_SHIPPING';
export type AffiliateCommissionType = 'PERCENTAGE' | 'FIXED_AMOUNT';

export type CouponRejectionCode =
    | 'COUPON_NOT_FOUND'
    | 'COUPON_INACTIVE'
    | 'COUPON_EXPIRED'
    | 'COUPON_EXHAUSTED'
    | 'CONDITIONS_NOT_MET'
    | 'NON_STACKABLE_LOWER_BENEFIT';

export type OrderStatus = 'PENDIENTE_PAGO' | 'COMPLETADA' | 'CANCELADA';
export type OrderTargetStatus = 'COMPLETADA' | 'CANCELADA';
export type CommissionStatus = 'RETENIDA' | 'APROBADA' | 'PAGADA' | 'ANULADA';
export type VoucherStatus = 'EMITIDO' | 'CANJEADO' | 'EXPIRADO';

export interface PortalShellPageProps {
    tenantSlug: string;
    tenantName: string;
    currencyCode: string;
    countryCode: string;
    urlMode: PortalUrlMode;
    [key: string]: unknown;
}

export interface TenantBranding {
    primary_color?: string | null;
    secondary_color?: string | null;
    accent_color?: string | null;
    font_family?: string | null;
    logo_url?: string | null;
    banner_url?: string | null;
    favicon_url?: string | null;
    social_links?: {
        whatsapp?: string | null;
        instagram?: string | null;
        facebook?: string | null;
        tiktok?: string | null;
    } | null;
}

export interface ShippingZone {
    code: string;
    name: string;
    fee: number;
}

export type StorefrontFilterItem =
    | {
          type: 'system';
          key: string;
          label: string;
          data_type: string;
          order: number;
      }
    | {
          type: 'metadata';
          key: string;
          label: string;
          data_type: MetadataDataType;
          affects_stock: boolean;
          order: number;
          options?: string[];
      };

export interface StorefrontBootstrapResponse {
    tenant: {
        name: string;
        currency_code: string;
        country_code: string;
    };
    branding: TenantBranding;
    filters: StorefrontFilterItem[];
    shipping_zones: ShippingZone[];
}

export interface CategoryNode {
    id: string;
    tenant_id: string;
    parent_id: string | null;
    name: string;
    slug: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    children?: CategoryNode[];
}

export interface StorefrontProductQuery {
    q?: string;
    category_slug?: string;
    category_id?: string;
    price_min?: number;
    price_max?: number;
    in_stock?: boolean;
    attr?: Record<string, string | number | boolean | (string | number)[]>;
    sort?: StorefrontProductSort;
    page?: number;
    per_page?: number;
}

export interface StorefrontMatchedVariant {
    id: string;
    sku: string;
    effective_price: number;
    stock: number;
    is_default: boolean;
    metadata: Record<string, unknown>;
}

export interface StorefrontProductCard {
    id: string;
    name: string;
    slug: string;
    base_price: number;
    metadata: Record<string, unknown>;
    images: string[];
    matched_variants: StorefrontMatchedVariant[];
}

export interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface StorefrontProductListResponse {
    data: StorefrontProductCard[];
    meta: PaginationMeta;
}

export interface FormattedVariantDetail {
    id: string;
    sku: string;
    price: number;
    price_override: number | null;
    stock: number;
    metadata: Record<string, unknown>;
    is_default: boolean;
    is_active: boolean;
}

export interface FormattedProductDetail {
    id: string;
    category_id: string | null;
    name: string;
    slug: string;
    description: string | null;
    base_price: number;
    metadata: Record<string, unknown>;
    images: string[];
    is_active: boolean;
    variants_count: number;
    total_stock: number;
    variants: FormattedVariantDetail[];
}

export interface CartQuotePayload {
    items: Array<{
        variant_id: string;
        quantity: number;
    }>;
    coupon_code?: string | null;
    shipping_zone_code?: string | null;
}

export interface CartQuotedItem {
    variant_id: string;
    product_name: string;
    sku: string;
    variant_attributes: Record<string, unknown>;
    quantity: number;
    available_stock: number;
    has_sufficient_stock: boolean;
    unit_price: number;
    line_total: number;
}

export interface CartQuoteResponse {
    is_valid: boolean;
    items: CartQuotedItem[];
    summary: {
        subtotal: number;
        auto_promotion: {
            applied: boolean;
            id: string | null;
            name: string | null;
            discount_amount: number;
        };
        coupon: {
            applied: boolean;
            id: string | null;
            code: string | null;
            discount_amount: number;
            rejection_code?: CouponRejectionCode;
            message?: string | null;
        };
        shipping: {
            zone_code: string | null;
            zone_name: string | null;
            shipping_fee: number;
            is_free: boolean;
        };
        total_net: number;
    };
}

export interface StorefrontCheckoutPayload {
    customer_info: {
        full_name: string;
        whatsapp_phone: string;
        email: string;
    };
    shipping_address: {
        region_code: string;
        address_line: string;
        reference?: string | null;
    };
    items: Array<{
        variant_id: string;
        quantity: number;
    }>;
    coupon_code?: string | null;
}

export interface StorefrontCheckoutResponse {
    order_id: string;
    order_number: string;
    status: OrderStatus;
    customer: {
        full_name: string;
        whatsapp_phone: string;
    };
    summary: {
        subtotal: number;
        auto_discount_total: number;
        coupon_discount_total: number;
        shipping_fee: number;
        total_net: number;
    };
    whatsapp_redirect_url: string;
    created_at: string;
}

export interface AdminUser {
    id: string;
    tenant_id?: string;
    name: string;
    email: string;
    role: UserRole;
    is_active?: boolean;
}

export interface AdminLoginResponse {
    access_token: string;
    token_type: 'Bearer';
    abilities: AdminTokenAbility[];
    expires_at: string;
    user: AdminUser;
    tenant: {
        id: string;
        name: string;
        slug: string;
    };
}

export interface AdminMeResponse {
    user: AdminUser;
    tenant: {
        id: string;
        name: string;
        slug: string;
    };
}

export interface MetadataAttributeDefinition {
    key: string;
    label: string;
    data_type: MetadataDataType;
    options?: string[] | null;
    is_required?: boolean;
    affects_stock?: boolean;
}

export interface FilterConfigDefinition {
    type: FilterConfigType;
    key: string;
    label?: string | null;
    data_type?: string | null;
    order?: number;
}

export interface BankAccountInfo {
    bank_name: string;
    account_number: string;
    account_type: string;
    holder_name: string;
}

export interface TenantContactInfo {
    whatsapp_sales?: string | null;
    support_email?: string | null;
    bank_accounts?: BankAccountInfo[] | null;
}

export interface OutboundRewardRules {
    is_enabled?: boolean;
    partner_store_id?: string | null;
    min_order_amount?: number | null;
    validity_days?: number | null;
}

export interface TenantConfigResponse {
    tenant_id: string;
    branding: TenantBranding;
    contact_info: TenantContactInfo;
    shipping_zones: ShippingZone[];
    metadata_schema: MetadataAttributeDefinition[];
    filter_config: FilterConfigDefinition[];
    outbound_reward_rules: OutboundRewardRules;
    updated_at: string;
}

export interface PartnerStoreAdmin {
    id: string;
    tenant_id: string;
    name: string;
    portal_email: string;
    benefit_title: string;
    terms_conditions: string;
    contact_info: Record<string, unknown>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    vouchers_count?: number;
    redeemed_count?: number;
}

export interface CreateProductVariantInput {
    id?: string | null;
    sku?: string | null;
    price?: number | null;
    stock: number;
    metadata?: Record<string, unknown> | null;
    is_default?: boolean;
    is_active?: boolean;
}

export interface UpsertProductPayload {
    category_id?: string | null;
    name: string;
    slug?: string | null;
    description?: string | null;
    base_price: number;
    images?: string[] | null;
    metadata?: Record<string, unknown> | null;
    is_active?: boolean;
    variants: CreateProductVariantInput[];
}

export interface Promotion {
    id: string;
    tenant_id: string;
    name: string;
    type: PromotionType;
    discount_type: DiscountType;
    discount_value: string | number;
    conditions: {
        min_subtotal?: number;
        min_quantity?: number;
        eligible_category_ids?: string[];
        eligible_variant_ids?: string[];
    };
    allow_stacking: boolean;
    starts_at: string;
    ends_at: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Affiliate {
    id: string;
    tenant_id: string;
    name: string;
    email: string;
    phone: string;
    commission_type: AffiliateCommissionType;
    commission_value: string | number;
    payment_info: Record<string, unknown>;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Coupon {
    id: string;
    tenant_id: string;
    promotion_id: string;
    affiliate_id: string | null;
    code: string;
    max_uses: number | null;
    used_count: number;
    starts_at: string;
    ends_at: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    promotion?: Promotion;
    affiliate?: Affiliate | null;
}

export interface Commission {
    id: string;
    tenant_id: string;
    order_id: string;
    affiliate_id: string;
    coupon_id: string;
    amount: string | number;
    status: CommissionStatus;
    paid_at: string | null;
    payment_reference: string | null;
    payment_receipt_url: string | null;
    created_at: string;
    updated_at: string;
    order?: {
        id: string;
        order_number: string;
        total_net: string | number;
        status: OrderStatus;
        created_at: string;
    };
    coupon?: Coupon;
    affiliate?: Affiliate;
}

export interface LaravelLengthAwarePaginator<T> {
    current_page: number;
    data: T[];
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AffiliateCommissionsLedgerResponse {
    affiliate: {
        id: string;
        name: string;
        email: string;
    };
    totals: {
        retained: number;
        approved: number;
        paid: number;
        voided: number;
    };
    commissions: LaravelLengthAwarePaginator<Commission>;
}

export interface OrderItem {
    id: string;
    tenant_id: string;
    order_id: string;
    product_id: string;
    variant_id: string;
    quantity: number;
    unit_price: string | number;
    line_total: string | number;
    variant_snapshot: {
        sku: string;
        product_name: string;
        attributes: Record<string, unknown>;
        unit_price: number;
    };
    created_at: string;
}

export interface OutboundRewardItem {
    id: string;
    code: string;
    status: VoucherStatus;
    expires_at: string;
    redeemed_at: string | null;
    partner_store?: PartnerStoreAdmin;
}

export interface OrderModel {
    id: string;
    tenant_id: string;
    order_number: string;
    status: OrderStatus;
    customer_info: {
        full_name: string;
        whatsapp_phone: string;
        email: string;
    };
    shipping_address: {
        region_code: string;
        address_line: string;
        reference?: string | null;
    };
    subtotal: string | number;
    auto_discount_total: string | number;
    coupon_discount_total: string | number;
    shipping_fee: string | number;
    total_net: string | number;
    applied_promotion_id: string | null;
    applied_coupon_id: string | null;
    whatsapp_url: string;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
    items: OrderItem[];
    commission: Commission | null;
    applied_promotion?: Promotion | null;
    applied_coupon?: Coupon | null;
    outbound_rewards?: OutboundRewardItem[];
}

export interface OrderTransitionResult {
    order_id: string;
    order_number: string;
    status: OrderTargetStatus;
    commission_status: CommissionStatus | null;
    outbound_reward_generated: boolean;
    outbound_reward_code: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
}

export interface PartnerLoginResponse {
    access_token: string;
    token_type: 'Bearer';
    abilities: PartnerTokenAbility[];
    expires_at: string;
    partner_store: {
        id: string;
        name: string;
        portal_email: string;
        benefit_title: string;
    };
    origin_tenant: {
        name: string;
        slug: string;
    };
}

export interface PartnerMeResponse {
    partner_store: {
        id: string;
        name: string;
        portal_email: string;
        benefit_title: string;
    };
    origin_tenant: {
        name: string;
        slug: string;
    };
}

export interface VoucherInspectionResponse {
    voucher_code: string;
    status: VoucherStatus;
    is_redeemable: boolean;
    benefit_title: string;
    terms_conditions: string;
    customer_name: string;
    origin_tenant_name: string;
    expires_at: string;
    redeemed_at: string | null;
}

export interface VoucherRedemptionResponse {
    voucher_code: string;
    status: 'CANJEADO';
    redeemed_at: string;
    message: string;
}
