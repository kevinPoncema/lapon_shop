<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\FilterProductsRequest;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StorefrontProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalogService
    ) {}

    public function index(FilterProductsRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $paginator = $this->catalogService->searchStorefrontProducts(
            $tenantContext->get(),
            $request->validated()
        );

        $formatted = collect($paginator->items())
            ->map(fn ($product) => $this->catalogService->formatProductForStorefrontResponse($product))
            ->all();

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(string $slug, TenantContext $tenantContext): JsonResponse
    {
        $product = $this->catalogService->getStorefrontProductDetail(
            $tenantContext->get(),
            $slug
        );

        return response()->json([
            'data' => $this->catalogService->formatProductForAdminResponse($product),
        ], Response::HTTP_OK);
    }
}
