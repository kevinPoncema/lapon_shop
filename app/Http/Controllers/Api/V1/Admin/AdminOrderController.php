<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAdminOrdersRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Services\Checkout\CheckoutOrchestratorService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminOrderController extends Controller
{
    public function __construct(
        protected CheckoutOrchestratorService $checkoutService
    ) {}

    public function index(FilterAdminOrdersRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $perPage = (int) ($request->input('per_page', 20));
        $orders = $this->checkoutService->paginateAdminOrders(
            $tenantContext->get(),
            $request->validated(),
            $perPage
        );

        return response()->json($orders, Response::HTTP_OK);
    }

    public function show(string $id, TenantContext $tenantContext): JsonResponse
    {
        $order = $this->checkoutService->getAdminOrderDetail(
            $tenantContext->get(),
            $id
        );

        return response()->json([
            'data' => $order,
        ], Response::HTTP_OK);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id, TenantContext $tenantContext): JsonResponse
    {
        $order = $this->checkoutService->orchestrateOrderStatusTransition(
            $tenantContext->get(),
            $id,
            (string) $request->input('status'),
            $request->input('payment_notes')
        );

        return response()->json([
            'data' => $order,
        ], Response::HTTP_OK);
    }
}
