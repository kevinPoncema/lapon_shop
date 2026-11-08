<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StorefrontCheckoutRequest;
use App\Services\Checkout\CheckoutOrchestratorService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCheckoutController extends Controller
{
    public function __construct(
        protected CheckoutOrchestratorService $checkoutService
    ) {}

    public function __invoke(StorefrontCheckoutRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $idempotencyKey = (string) $request->header('Idempotency-Key');

        $result = $this->checkoutService->orchestrateCheckout(
            $tenantContext->get(),
            $request->validated(),
            $idempotencyKey
        );

        $status = $result['is_replay'] ? Response::HTTP_OK : Response::HTTP_CREATED;
        $response = response()->json($result['payload'], $status);

        if ($result['is_replay']) {
            $response->header('X-Idempotent-Replay', 'true');
        }

        return $response;
    }
}
