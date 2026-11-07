<?php

namespace App\Exceptions\Domain;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CommissionNotSettleableException extends Exception
{
    public function __construct(string $message = 'Solo se pueden liquidar comisiones en estado APROBADA. Una o más comisiones seleccionadas no cumplen esta condición.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'COMMISSION_NOT_SETTLEABLE',
                'message' => $this->getMessage(),
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
