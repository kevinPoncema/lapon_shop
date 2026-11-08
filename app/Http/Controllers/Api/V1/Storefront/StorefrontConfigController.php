<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantConfigService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StorefrontConfigController extends Controller
{
    public function __construct(
        protected TenantConfigService $tenantConfigService
    ) {}

    /**
     * Entrega bootstrap público para renderizar la tienda.
     */
    public function __invoke(TenantContext $tenantContext): JsonResponse
    {
        $bootstrap = $this->tenantConfigService->getStorefrontBootstrap(
            $tenantContext->get()
        );

        return response()->json($bootstrap, Response::HTTP_OK);
    }
}
