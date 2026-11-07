<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminCategoryController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalogService
    ) {}

    public function index(TenantContext $tenantContext): JsonResponse
    {
        $categories = $this->catalogService->getAdminCategoryTree($tenantContext->get());

        return response()->json([
            'data' => $categories,
        ], Response::HTTP_OK);
    }

    public function store(CategoryRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $category = $this->catalogService->orchestrateCategoryCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $category,
        ], Response::HTTP_CREATED);
    }
}
