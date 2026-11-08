<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Repositories\Contracts\PromotionRepositoryInterface;
use App\Services\Cart\CartPricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminPromotionController extends Controller
{
    public function __construct(
        protected CartPricingService $pricingService,
        protected PromotionRepositoryInterface $promotionRepository
    ) {}

    public function index(): JsonResponse
    {
        $promotions = $this->promotionRepository->listForAdmin();

        return response()->json([
            'data' => $promotions,
        ], Response::HTTP_OK);
    }

    public function store(CreatePromotionRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $promotion = $this->pricingService->orchestratePromotionCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $promotion,
        ], Response::HTTP_CREATED);
    }

    public function update(UpdatePromotionRequest $request, string $id, TenantContext $tenantContext): JsonResponse
    {
        $promotion = $this->pricingService->orchestratePromotionUpdate(
            $tenantContext->get(),
            $id,
            $request->validated()
        );

        return response()->json([
            'data' => $promotion,
        ], Response::HTTP_OK);
    }
}
