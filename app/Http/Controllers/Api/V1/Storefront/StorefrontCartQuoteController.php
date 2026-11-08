<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartQuoteRequest;
use App\Services\Cart\CartPricingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCartQuoteController extends Controller
{
    public function __construct(
        protected CartPricingService $pricingService
    ) {}

    public function quote(CartQuoteRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $quote = $this->pricingService->calculateCartQuote(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($quote, Response::HTTP_OK);
    }
}
