<?php

namespace App\Http\Middleware;

use App\Context\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
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
        $actor = Auth::guard('sanctum')->user() ?? $request->user();

        if (! $actor) {
            return new JsonResponse([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'No autenticado.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! ($actor instanceof User)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Acceso denegado: El tipo de actor no tiene privilegios administrativos.',
                ],
            ], Response::HTTP_FORBIDDEN);
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

        if ($actor->tenant_id !== $activeTenantId || ! (bool) $actor->is_active) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CROSS_TENANT_ACCESS_DENIED',
                    'message' => 'Acceso denegado: El usuario no pertenece al inquilino solicitado o se encuentra inactivo.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var PersonalAccessToken|null $token */
        $token = $actor->currentAccessToken();
        if ($token?->exists && ! $actor->tokenCan('tenant:admin') && ! $actor->tokenCan('tenant:staff') && ! $actor->tokenCan('*')) {
            return new JsonResponse([
                'error' => [
                    'code' => 'FORBIDDEN_SCOPE',
                    'message' => 'El token no posee el alcance administrativo requerido.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
