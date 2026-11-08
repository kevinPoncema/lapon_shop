<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\User;
use App\Services\Auth\TenantAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthController extends Controller
{
    public function __construct(
        protected TenantAuthenticationService $authService
    ) {}

    /**
     * Authenticate an admin user and issue a Bearer token.
     */
    public function login(
        AdminLoginRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $dto = $this->authService->orchestrateAdminLogin(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($dto->toArray(), Response::HTTP_OK);
    }

    /**
     * Return the authenticated admin profile and current tenant summary.
     */
    public function me(
        Request $request,
        TenantContext $tenantContext
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $tenant = $tenantContext->get();

        return response()->json([
            'user' => $user,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Revoke the current access token for this device.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->revokeCurrentToken($user);

        return response()->json([
            'message' => 'Sesión cerrada exitosamente en este dispositivo.',
        ], Response::HTTP_OK);
    }

    /**
     * Revoke all active tokens for this user across all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->revokeAllTokens($user);

        return response()->json([
            'message' => 'Se han cerrado todas las sesiones activas.',
        ], Response::HTTP_OK);
    }
}
