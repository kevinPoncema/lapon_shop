<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAffiliateRequest;
use App\Repositories\Contracts\AffiliateRepositoryInterface;
use App\Services\Cart\CartPricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminAffiliateController extends Controller
{
    public function __construct(
        protected CartPricingService $pricingService,
        protected AffiliateRepositoryInterface $affiliateRepository
    ) {}

    public function index(): JsonResponse
    {
        $affiliates = $this->affiliateRepository->listForAdmin();

        return response()->json([
            'data' => $affiliates,
        ], Response::HTTP_OK);
    }

    public function store(CreateAffiliateRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $affiliate = $this->pricingService->orchestrateAffiliateCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $affiliate,
        ], Response::HTTP_CREATED);
    }
}
