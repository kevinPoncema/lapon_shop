import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { ApiClientError, commerceApi } from '@/lib/api-client';
import {
    type PortalRouteContext,
    type PortalUrlMode,
} from '@/lib/portal-paths';
import {
    type CategoryNode,
    type StorefrontBootstrapResponse,
} from '@/types/commerce';

interface TenantContextValue {
    tenantSlug: string;
    tenantName: string;
    currencyCode: string;
    countryCode: string;
    urlMode: PortalUrlMode;
    routeCtx: PortalRouteContext;
    bootstrap: StorefrontBootstrapResponse | null;
    categories: CategoryNode[];
    isLoadingConfig: boolean;
    configError: string | null;
    refreshBootstrap: () => Promise<void>;
}

const TenantContext = createContext<TenantContextValue | null>(null);

const HEX_COLOR_REGEX = /^#[0-9A-Fa-f]{6}$/;

interface TenantProviderProps {
    tenantSlug: string;
    tenantName: string;
    currencyCode: string;
    countryCode: string;
    urlMode: PortalUrlMode;
    includeCategories?: boolean;
    children: ReactNode;
}

export function TenantProvider({
    tenantSlug,
    tenantName,
    currencyCode,
    countryCode,
    urlMode,
    includeCategories = false,
    children,
}: TenantProviderProps) {
    const [bootstrap, setBootstrap] =
        useState<StorefrontBootstrapResponse | null>(null);
    const [categories, setCategories] = useState<CategoryNode[]>([]);
    const [isLoadingConfig, setIsLoadingConfig] = useState<boolean>(true);
    const [configError, setConfigError] = useState<string | null>(null);

    const loadBootstrap = useCallback(
        async (signal?: AbortSignal) => {
            setIsLoadingConfig(true);
            setConfigError(null);

            try {
                if (includeCategories) {
                    const [configData, categoryRes] = await Promise.all([
                        commerceApi.storefront.getConfig(tenantSlug, signal),
                        commerceApi.storefront.getCategories(
                            tenantSlug,
                            signal,
                        ),
                    ]);
                    setBootstrap(configData);
                    setCategories(categoryRes.data);
                } else {
                    const configData = await commerceApi.storefront.getConfig(
                        tenantSlug,
                        signal,
                    );
                    setBootstrap(configData);
                }
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                const message =
                    err instanceof ApiClientError
                        ? err.message
                        : 'No se pudo cargar la configuración de la tienda.';
                setConfigError(message);
            } finally {
                if (!signal?.aborted) {
                    setIsLoadingConfig(false);
                }
            }
        },
        [tenantSlug, includeCategories],
    );

    useEffect(() => {
        const controller = new AbortController();
        void loadBootstrap(controller.signal);

        return () => {
            controller.abort();
        };
    }, [loadBootstrap]);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const root = document.documentElement;
        const branding = bootstrap?.branding;

        if (
            branding?.primary_color &&
            HEX_COLOR_REGEX.test(branding.primary_color)
        ) {
            root.style.setProperty('--tenant-primary', branding.primary_color);
        } else {
            root.style.removeProperty('--tenant-primary');
        }

        if (
            branding?.secondary_color &&
            HEX_COLOR_REGEX.test(branding.secondary_color)
        ) {
            root.style.setProperty(
                '--tenant-secondary',
                branding.secondary_color,
            );
        } else {
            root.style.removeProperty('--tenant-secondary');
        }

        if (
            branding?.accent_color &&
            HEX_COLOR_REGEX.test(branding.accent_color)
        ) {
            root.style.setProperty('--tenant-accent', branding.accent_color);
        } else {
            root.style.removeProperty('--tenant-accent');
        }

        if (branding?.font_family && branding.font_family.trim().length > 0) {
            root.style.setProperty(
                '--tenant-font-sans',
                `'${branding.font_family.trim()}'`,
            );
        } else {
            root.style.removeProperty('--tenant-font-sans');
        }

        return () => {
            root.style.removeProperty('--tenant-primary');
            root.style.removeProperty('--tenant-secondary');
            root.style.removeProperty('--tenant-accent');
            root.style.removeProperty('--tenant-font-sans');
        };
    }, [bootstrap?.branding]);

    const routeCtx = useMemo<PortalRouteContext>(
        () => ({
            tenantSlug,
            urlMode,
        }),
        [tenantSlug, urlMode],
    );

    const refreshBootstrap = useCallback(async () => {
        await loadBootstrap();
    }, [loadBootstrap]);

    const value = useMemo<TenantContextValue>(
        () => ({
            tenantSlug,
            tenantName: bootstrap?.tenant.name ?? tenantName,
            currencyCode: bootstrap?.tenant.currency_code ?? currencyCode,
            countryCode: bootstrap?.tenant.country_code ?? countryCode,
            urlMode,
            routeCtx,
            bootstrap,
            categories,
            isLoadingConfig,
            configError,
            refreshBootstrap,
        }),
        [
            tenantSlug,
            tenantName,
            currencyCode,
            countryCode,
            urlMode,
            routeCtx,
            bootstrap,
            categories,
            isLoadingConfig,
            configError,
            refreshBootstrap,
        ],
    );

    return (
        <TenantContext.Provider value={value}>
            {children}
        </TenantContext.Provider>
    );
}

export function useTenant(): TenantContextValue {
    const context = useContext(TenantContext);
    if (!context) {
        throw new Error('useTenant debe usarse dentro de un TenantProvider.');
    }
    return context;
}
