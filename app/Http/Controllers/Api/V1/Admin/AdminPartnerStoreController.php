<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePartnerStoreRequest;
use App\Http\Requests\Admin\UpdatePartnerStoreRequest;
use App\Services\Partners\PartnerStoreService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminPartnerStoreController extends Controller
{
    public function __construct(
        protected PartnerStoreService $partnerStoreService
    ) {}

    /**
     * List all partner stores for the authenticated tenant with voucher metrics.
     */
    public function index(): JsonResponse
    {
        $partners = $this->partnerStoreService->listPartnerStoresWithMetrics();

        return response()->json([
            'data' => $partners,
        ], Response::HTTP_OK);
    }

    /**
     * Create a new partner store for the current tenant.
     */
    public function store(
        CreatePartnerStoreRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $partner = $this->partnerStoreService->orchestratePartnerCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $partner,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing partner store for the current tenant.
     */
    public function update(
        UpdatePartnerStoreRequest $request,
        TenantContext $tenantContext,
        string $id
    ): JsonResponse {
        $partner = $this->partnerStoreService->orchestratePartnerUpdate(
            $tenantContext->get(),
            $id,
            $request->validated()
        );

        return response()->json([
            'data' => $partner,
        ], Response::HTTP_OK);
    }
}
