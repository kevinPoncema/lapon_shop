<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutTransactionInProgressException extends Exception
{
    public function __construct(string $message = 'Una transacción idéntica ya se encuentra en proceso.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'TRANSACTION_IN_PROGRESS',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_CONFLICT);
    }
}
