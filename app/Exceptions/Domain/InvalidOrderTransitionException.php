<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidOrderTransitionException extends Exception
{
    public function __construct(string $message = 'La orden no se encuentra en un estado que permita esta transición.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_ORDER_TRANSITION',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_CONFLICT);
    }
}
