<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VoucherNotFoundException extends Exception
{
    public function __construct(string $message = 'El cupón no fue encontrado o no pertenece a este comercio aliado.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'VOUCHER_NOT_FOUND',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_NOT_FOUND);
    }
}
