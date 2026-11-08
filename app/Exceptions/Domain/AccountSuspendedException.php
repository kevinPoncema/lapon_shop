<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountSuspendedException extends Exception
{
    public function __construct(string $message = 'La cuenta se encuentra suspendida o inactiva.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
