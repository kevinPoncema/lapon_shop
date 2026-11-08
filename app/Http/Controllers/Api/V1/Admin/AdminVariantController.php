<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustVariantStockRequest;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminVariantController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalogService
    ) {}

    public function adjustStock(
        AdjustVariantStockRequest $request,
        string $id,
        TenantContext $tenantContext
    ): JsonResponse {
        $result = $this->catalogService->orchestrateStockAdjustment(
            tenant: $tenantContext->get(),
            variantId: $id,
            mode: (string) $request->input('mode'),
            quantity: (int) $request->input('quantity'),
            reason: $request->input('reason')
        );

        return response()->json([
            'data' => $result,
        ], Response::HTTP_OK);
    }

    public function updateStatus(
        Request $request,
        string $id,
        TenantContext $tenantContext
    ): JsonResponse {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $variant = $this->catalogService->orchestrateVariantStatusUpdate(
            tenant: $tenantContext->get(),
            variantId: $id,
            isActive: (bool) $validated['is_active']
        );

        return response()->json([
            'data' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'is_active' => $variant->is_active,
                'is_default' => $variant->is_default,
            ],
        ], Response::HTTP_OK);
    }
}
