import { home } from '@/routes';
import subdomain from '@/routes/subdomain';
import tenant from '@/routes/tenant';
import { type RouteQueryOptions } from '@/wayfinder';

export type PortalUrlMode = 'path' | 'subdomain';

export interface PortalRouteContext {
    tenantSlug: string;
    urlMode: PortalUrlMode;
}

export const portalPaths = {
    home: (): string => home.url(),

    storefront: {
        catalog: (
            ctx: PortalRouteContext,
            options?: RouteQueryOptions,
        ): string =>
            ctx.urlMode === 'subdomain'
                ? home.url(options)
                : tenant.storefront.catalog.url(ctx.tenantSlug, options),

        product: (
            ctx: PortalRouteContext,
            productSlug: string,
            options?: RouteQueryOptions,
        ): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.storefront.product.url({ productSlug }, options)
                : tenant.storefront.product.url(
                      { tenantSlug: ctx.tenantSlug, productSlug },
                      options,
                  ),

        checkout: (
            ctx: PortalRouteContext,
            options?: RouteQueryOptions,
        ): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.storefront.checkout.url(options)
                : tenant.storefront.checkout.url(ctx.tenantSlug, options),
    },

    admin: {
        login: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.login.url()
                : tenant.admin.login.url(ctx.tenantSlug),

        orders: (
            ctx: PortalRouteContext,
            options?: RouteQueryOptions,
        ): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.orders.url(options)
                : tenant.admin.orders.url(ctx.tenantSlug, options),

        catalog: (
            ctx: PortalRouteContext,
            options?: RouteQueryOptions,
        ): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.catalog.url(options)
                : tenant.admin.catalog.url(ctx.tenantSlug, options),

        schema: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.schema.url()
                : tenant.admin.schema.url(ctx.tenantSlug),

        marketing: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.marketing.url()
                : tenant.admin.marketing.url(ctx.tenantSlug),

        affiliates: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.affiliates.url()
                : tenant.admin.affiliates.url(ctx.tenantSlug),

        partners: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.admin.partners.url()
                : tenant.admin.partners.url(ctx.tenantSlug),
    },

    partner: {
        login: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.partner.login.url()
                : tenant.partner.login.url(ctx.tenantSlug),

        pos: (ctx: PortalRouteContext): string =>
            ctx.urlMode === 'subdomain'
                ? subdomain.partner.pos.url()
                : tenant.partner.pos.url(ctx.tenantSlug),
    },
};
