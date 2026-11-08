<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCouponRequest;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Services\Cart\CartPricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminCouponController extends Controller
{
    public function __construct(
        protected CartPricingService $pricingService,
        protected CouponRepositoryInterface $couponRepository
    ) {}

    public function index(): JsonResponse
    {
        $coupons = $this->couponRepository->listForAdmin();

        return response()->json([
            'data' => $coupons,
        ], Response::HTTP_OK);
    }

    public function store(CreateCouponRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $coupon = $this->pricingService->orchestrateCouponCreation(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json([
            'data' => $coupon,
        ], Response::HTTP_CREATED);
    }
}
