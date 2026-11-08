<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCategoryController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalogService
    ) {}

    public function __invoke(TenantContext $tenantContext): JsonResponse
    {
        $categories = $this->catalogService->getStorefrontCategoryTree($tenantContext->get());

        return response()->json([
            'data' => $categories,
        ], Response::HTTP_OK);
    }
}
