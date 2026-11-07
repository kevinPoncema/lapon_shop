<?php

namespace App\Http\Middleware;

use App\Context\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantMembership
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'No autenticado.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->tenantContext->isSet()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'El inquilino solicitado no existe o se encuentra inactivo.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $activeTenantId = $this->tenantContext->id();

        if ($user->tenant_id !== $activeTenantId || ! (bool) $user->is_active) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CROSS_TENANT_ACCESS_DENIED',
                    'message' => 'Acceso denegado: El usuario no pertenece al inquilino solicitado o se encuentra inactivo.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
