<?php

namespace App\Http\Middleware;

use App\Context\TenantContext;
use App\Models\PartnerStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerMembership
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->user();

        if (! $partner || ! ($partner instanceof PartnerStore)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'No autenticado o credenciales de comercio aliado inválidas.',
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

        if ($partner->tenant_id !== $activeTenantId || ! (bool) $partner->is_active) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CROSS_TENANT_ACCESS_DENIED',
                    'message' => 'Acceso denegado: El comercio aliado no pertenece al inquilino solicitado o se encuentra inactivo.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        if (method_exists($partner, 'currentAccessToken') && $partner->currentAccessToken() && method_exists($partner, 'tokenCan')) {
            if (! $partner->tokenCan('partner-portal') && ! $partner->tokenCan('*')) {
                return new JsonResponse([
                    'error' => [
                        'code' => 'FORBIDDEN_SCOPE',
                        'message' => 'El token no posee el alcance requerido (partner-portal).',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
