<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalogService,
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function index(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $paginator = $this->productRepository->paginateForAdmin($perPage, $request->query());

        $formatted = collect($paginator->items())
            ->map(fn ($product) => $this->catalogService->formatProductForAdminResponse($product))
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

    public function store(CreateProductRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $product = $this->catalogService->orchestrateProductCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $this->catalogService->formatProductForAdminResponse($product),
        ], Response::HTTP_CREATED);
    }

    public function show(string $id, TenantContext $tenantContext): JsonResponse
    {
        $product = $this->productRepository->findByIdWithVariants($id);

        if ($product === null) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_NOT_FOUND',
                    'message' => 'Producto no encontrado.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $this->catalogService->formatProductForAdminResponse($product),
        ], Response::HTTP_OK);
    }

    public function update(UpdateProductRequest $request, string $id, TenantContext $tenantContext): JsonResponse
    {
        $product = $this->catalogService->orchestrateProductUpdate(
            $tenantContext->get(),
            $id,
            $request->validated()
        );

        return response()->json([
            'data' => $this->catalogService->formatProductForAdminResponse($product),
        ], Response::HTTP_OK);
    }
}
