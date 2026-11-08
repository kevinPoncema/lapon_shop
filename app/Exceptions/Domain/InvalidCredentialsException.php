<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'Las credenciales proporcionadas son incorrectas.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
