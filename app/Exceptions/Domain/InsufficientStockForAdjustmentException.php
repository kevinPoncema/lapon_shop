<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockForAdjustmentException extends Exception
{
    public function __construct(string $message = 'Stock insuficiente para realizar el ajuste.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INSUFFICIENT_STOCK_FOR_ADJUSTMENT',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
