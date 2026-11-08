<?php

namespace App\Http\Middleware;

use App\Context\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->extractSlug($request);

        if ($slug === null) {
            return $this->buildNotFoundResponse();
        }

        $tenant = $this->resolveTenantWithCache($slug);

        if (! $this->isTenantAvailable($tenant)) {
            return $this->buildNotFoundResponse();
        }

        $this->hydrateTenantContext($tenant, $request);

        return $next($request);
    }

    private function extractSlug(Request $request): ?string
    {
        $headerSlug = $request->header('X-Tenant-Slug');

        if (is_string($headerSlug) && trim($headerSlug) !== '') {
            return Str::lower(trim($headerSlug));
        }

        $host = $request->getHost();
        $hostSegments = explode('.', $host);

        if (count($hostSegments) >= 2 && $hostSegments[0] !== 'www' && $hostSegments[0] !== 'api') {
            return Str::lower(trim($hostSegments[0]));
        }

        return null;
    }

    private function resolveTenantWithCache(string $slug): ?Tenant
    {
        return Tenant::findCachedBySlug($slug);
    }

    private function isTenantAvailable(?Tenant $tenant): bool
    {
        return $tenant !== null && (bool) $tenant->is_active;
    }

    private function hydrateTenantContext(Tenant $tenant, Request $request): void
    {
        $this->tenantContext->set($tenant);
        $request->attributes->set('tenant', $tenant);
    }

    private function buildNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
            ],
        ], Response::HTTP_NOT_FOUND);
    }
}
