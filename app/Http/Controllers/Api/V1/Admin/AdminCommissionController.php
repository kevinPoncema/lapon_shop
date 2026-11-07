<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettleCommissionsRequest;
use App\Services\Commissions\CommissionSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminCommissionController extends Controller
{
    public function __construct(
        protected CommissionSettlementService $settlementService
    ) {}

    public function index(Request $request, string $affiliateId): JsonResponse
    {
        $perPage = (int) ($request->input('per_page', 20));
        $ledger = $this->settlementService->getAffiliateLedger(
            $affiliateId,
            $request->query(),
            $perPage
        );

        return response()->json($ledger, Response::HTTP_OK);
    }

    public function settle(SettleCommissionsRequest $request, string $affiliateId): JsonResponse
    {
        $result = $this->settlementService->orchestrateCommissionSettlement(
            $affiliateId,
            $request->commissionIds(),
            (string) $request->input('payment_reference'),
            $request->input('payment_receipt_url')
        );

        return response()->json($result, Response::HTTP_OK);
    }
}
