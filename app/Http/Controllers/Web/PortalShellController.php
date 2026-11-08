<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalShellController extends Controller
{
    public function home(Request $request): Response
    {
        $subdomainTenant = $this->resolveSubdomainTenant($request);

        if ($subdomainTenant !== null) {
            return Inertia::render('storefront/catalog', $this->tenantShellProps($subdomainTenant, 'subdomain'));
        }

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'slug', 'country_code', 'currency_code'])
            ->orderBy('name')
            ->limit(18)
            ->get();

        return Inertia::render('welcome', [
            'tenants' => $tenants,
        ]);
    }

    public function storefrontCatalog(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('storefront/catalog', $this->tenantShellProps($tenant, 'path'));
    }

    public function storefrontProduct(string $tenantSlug, string $productSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('storefront/product-detail', [
            ...$this->tenantShellProps($tenant, 'path'),
            'productSlug' => $productSlug,
        ]);
    }

    public function storefrontCheckout(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('storefront/checkout', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminLogin(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/login', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminOrders(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/orders', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminCatalog(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/catalog', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminSchema(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/schema-settings', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminMarketing(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/marketing', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminAffiliates(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/affiliates', $this->tenantShellProps($tenant, 'path'));
    }

    public function adminPartners(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('tenant-admin/partners', $this->tenantShellProps($tenant, 'path'));
    }

    public function partnerLogin(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('partner-portal/login', $this->tenantShellProps($tenant, 'path'));
    }

    public function partnerPos(string $tenantSlug): Response
    {
        $tenant = $this->requirePathTenant($tenantSlug);

        return Inertia::render('partner-portal/pos-scanner', $this->tenantShellProps($tenant, 'path'));
    }

    public function subdomainStorefrontProduct(Request $request, string $productSlug): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('storefront/product-detail', [
            ...$this->tenantShellProps($tenant, 'subdomain'),
            'productSlug' => $productSlug,
        ]);
    }

    public function subdomainStorefrontCheckout(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('storefront/checkout', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminLogin(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/login', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminOrders(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/orders', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminCatalog(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/catalog', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminSchema(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/schema-settings', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminMarketing(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/marketing', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminAffiliates(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/affiliates', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainAdminPartners(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('tenant-admin/partners', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainPartnerLogin(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('partner-portal/login', $this->tenantShellProps($tenant, 'subdomain'));
    }

    public function subdomainPartnerPos(Request $request): Response
    {
        $tenant = $this->requireSubdomainTenant($request);

        return Inertia::render('partner-portal/pos-scanner', $this->tenantShellProps($tenant, 'subdomain'));
    }

    /**
     * @return array{tenantSlug: string, tenantName: string, currencyCode: string, countryCode: string, urlMode: 'path'|'subdomain'}
     */
    private function tenantShellProps(Tenant $tenant, string $urlMode): array
    {
        /** @var 'path'|'subdomain' $mode */
        $mode = $urlMode === 'subdomain' ? 'subdomain' : 'path';

        return [
            'tenantSlug' => $tenant->slug,
            'tenantName' => $tenant->name,
            'currencyCode' => $tenant->currency_code,
            'countryCode' => $tenant->country_code,
            'urlMode' => $mode,
        ];
    }

    private function requirePathTenant(string $tenantSlug): Tenant
    {
        $tenant = $this->findActiveTenantBySlug($tenantSlug);

        if ($tenant === null) {
            abort(404);
        }

        return $tenant;
    }

    private function requireSubdomainTenant(Request $request): Tenant
    {
        $tenant = $this->resolveSubdomainTenant($request);

        if ($tenant === null) {
            abort(404);
        }

        return $tenant;
    }

    private function resolveSubdomainTenant(Request $request): ?Tenant
    {
        $host = Str::lower(trim($request->getHost()));

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $baseHost = Str::lower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === $baseHost) {
            return null;
        }

        $segments = explode('.', $host);

        if (count($segments) < 2) {
            return null;
        }

        $candidateSlug = trim($segments[0]);

        if (in_array($candidateSlug, ['', 'www', 'api', 'localhost'], true)) {
            return null;
        }

        return $this->findActiveTenantBySlug($candidateSlug);
    }

    private function findActiveTenantBySlug(string $slug): ?Tenant
    {
        $tenant = Tenant::findCachedBySlug($slug);

        if ($tenant === null || ! $tenant->is_active) {
            return null;
        }

        return $tenant;
    }
}
