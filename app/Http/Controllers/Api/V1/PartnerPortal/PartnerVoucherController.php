<?php

namespace App\Http\Controllers\Api\V1\PartnerPortal;

use App\Http\Controllers\Controller;
use App\Models\PartnerStore;
use App\Services\Partners\PartnerVoucherRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerVoucherController extends Controller
{
    public function __construct(
        protected PartnerVoucherRedemptionService $partnerVoucherRedemptionService
    ) {}

    /**
     * Inspect a voucher code for the authenticated partner store.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        /** @var PartnerStore $partner */
        $partner = $request->user();

        $dto = $this->partnerVoucherRedemptionService->inspectVoucher($partner, $code);

        return response()->json($dto->toArray(), Response::HTTP_OK);
    }

    /**
     * Redeem a voucher code for the authenticated partner store.
     */
    public function redeem(Request $request, string $code): JsonResponse
    {
        /** @var PartnerStore $partner */
        $partner = $request->user();

        $dto = $this->partnerVoucherRedemptionService->orchestrateRedemption($partner, $code);

        return response()->json($dto->toArray(), Response::HTTP_OK);
    }
}
