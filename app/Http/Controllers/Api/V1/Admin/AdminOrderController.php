<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterOrdersRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderLifecycleService $lifecycleService
    ) {}

    public function index(FilterOrdersRequest $request): JsonResponse
    {
        $perPage = (int) ($request->input('per_page', 20));
        $orders = $this->lifecycleService->paginateOrders(
            $request->validated(),
            $perPage
        );

        return response()->json($orders, Response::HTTP_OK);
    }

    public function show(string $id): JsonResponse
    {
        $order = $this->lifecycleService->getOrderDetail($id);

        return response()->json([
            'data' => $order,
        ], Response::HTTP_OK);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        $notes = $request->input('payment_notes') ?? $request->input('cancellation_reason');

        $result = $this->lifecycleService->orchestrateStatusTransition(
            $id,
            (string) $request->input('status'),
            $notes
        );

        return response()->json(
            array_merge($result, ['data' => $result]),
            Response::HTTP_OK
        );
    }
}
