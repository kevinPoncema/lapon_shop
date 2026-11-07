<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingConfigRequest;
use App\Http\Requests\Admin\UpdateCatalogSchemaRequest;
use App\Http\Requests\Admin\UpdateShippingZonesRequest;
use App\Services\Tenant\TenantConfigService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminTenantConfigController extends Controller
{
    public function __construct(
        protected TenantConfigService $tenantConfigService
    ) {}

    /**
     * Retorna la configuración integral del comercio para el panel de administración.
     */
    public function show(TenantContext $tenantContext): JsonResponse
    {
        $config = $this->tenantConfigService->getAdminConfig($tenantContext->get());

        return response()->json($config, Response::HTTP_OK);
    }

    /**
     * Actualiza metadata_schema y filter_config.
     */
    public function updateCatalogSchema(
        UpdateCatalogSchemaRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $updated = $this->tenantConfigService->orchestrateCatalogSchemaUpdate(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($updated, Response::HTTP_OK);
    }

    /**
     * Actualiza colores, fuentes, URLs de marca y redes sociales.
     */
    public function updateBranding(
        UpdateBrandingConfigRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $updated = $this->tenantConfigService->orchestrateBrandingUpdate(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($updated, Response::HTTP_OK);
    }

    /**
     * Actualiza zonas de despacho y sus tarifas logísticas.
     */
    public function updateShippingZones(
        UpdateShippingZonesRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $updated = $this->tenantConfigService->orchestrateShippingZonesUpdate(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($updated, Response::HTTP_OK);
    }
}
