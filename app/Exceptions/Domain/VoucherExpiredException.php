<?php

namespace App\Exceptions\Domain;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VoucherExpiredException extends Exception
{
    public function __construct(
        protected CarbonInterface|string|null $expiresAt = null,
        string $message = 'Este cupón expiró y ya no puede ser canjeado.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        $expiresAtString = null;
        if ($this->expiresAt instanceof CarbonInterface) {
            $expiresAtString = $this->expiresAt->toISOString();
        } elseif (is_string($this->expiresAt)) {
            $expiresAtString = $this->expiresAt;
        }

        return response()->json([
            'error' => [
                'code' => 'VOUCHER_EXPIRED',
                'message' => $this->getMessage(),
                'expires_at' => $expiresAtString,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
