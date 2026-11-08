<?php

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VoucherAlreadyRedeemedException extends Exception
{
    public function __construct(
        protected CarbonInterface|string|null $redeemedAt = null,
        string $message = 'Este cupón ya fue canjeado previamente.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $redeemedAtString = null;
        if ($this->redeemedAt instanceof CarbonInterface) {
            $redeemedAtString = $this->redeemedAt->toISOString();
        } elseif (is_string($this->redeemedAt)) {
            $redeemedAtString = $this->redeemedAt;
        }

        return response()->json([
            'error' => [
                'code' => 'VOUCHER_ALREADY_REDEEMED',
                'message' => $this->getMessage(),
                'redeemed_at' => $redeemedAtString,
            ],
        ], Response::HTTP_CONFLICT);
    }
}
