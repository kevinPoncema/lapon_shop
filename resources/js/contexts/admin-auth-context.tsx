import { router } from '@inertiajs/react';
import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { useTenant } from '@/contexts/tenant-context';
import { ApiClientError, commerceApi, tokenStorage } from '@/lib/api-client';
import { portalPaths } from '@/lib/portal-paths';
import { type AdminUser } from '@/types/commerce';

interface AdminAuthContextValue {
    user: AdminUser | null;
    isCheckingAuth: boolean;
    logout: () => Promise<void>;
}

const AdminAuthContext = createContext<AdminAuthContextValue | null>(null);

export function AdminAuthProvider({ children }: { children: ReactNode }) {
    const { tenantSlug, routeCtx } = useTenant();
    const [user, setUser] = useState<AdminUser | null>(null);
    const [isCheckingAuth, setIsCheckingAuth] = useState<boolean>(true);

    useEffect(() => {
        const token = tokenStorage.getAdminToken(tenantSlug);
        if (!token) {
            router.visit(portalPaths.admin.login(routeCtx), { replace: true });
            return;
        }

        const controller = new AbortController();
        setIsCheckingAuth(true);

        void (async () => {
            try {
                const res = await commerceApi.admin.me(
                    tenantSlug,
                    controller.signal,
                );
                setUser(res.user);
            } catch (err) {
                if (err instanceof DOMException && err.name === 'AbortError') {
                    return;
                }
                if (
                    err instanceof ApiClientError &&
                    (err.status === 401 || err.status === 403)
                ) {
                    tokenStorage.clearAdminToken(tenantSlug);
                    router.visit(portalPaths.admin.login(routeCtx), {
                        replace: true,
                    });
                    return;
                }
                tokenStorage.clearAdminToken(tenantSlug);
                router.visit(portalPaths.admin.login(routeCtx), {
                    replace: true,
                });
            } finally {
                if (!controller.signal.aborted) {
                    setIsCheckingAuth(false);
                }
            }
        })();

        return () => controller.abort();
    }, [tenantSlug, routeCtx]);

    const logout = useCallback(async () => {
        try {
            await commerceApi.admin.logout(tenantSlug);
        } catch {
            // Ignore network error on token revocation
        } finally {
            tokenStorage.clearAdminToken(tenantSlug);
            router.visit(portalPaths.admin.login(routeCtx), { replace: true });
        }
    }, [tenantSlug, routeCtx]);

    const value = useMemo<AdminAuthContextValue>(
        () => ({
            user,
            isCheckingAuth,
            logout,
        }),
        [user, isCheckingAuth, logout],
    );

    return (
        <AdminAuthContext.Provider value={value}>
            {children}
        </AdminAuthContext.Provider>
    );
}

export function useAdminAuth(): AdminAuthContextValue {
    const context = useContext(AdminAuthContext);
    if (!context) {
        throw new Error(
            'useAdminAuth debe utilizarse dentro de AdminAuthProvider.',
        );
    }
    return context;
}
