<?php

namespace App\Http\Controllers\Api\V1\PartnerPortal;

use App\Context\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PartnerPortalLoginRequest;
use App\Models\PartnerStore;
use App\Services\Auth\TenantAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerPortalAuthController extends Controller
{
    public function __construct(
        protected TenantAuthenticationService $authService
    ) {}

    /**
     * Authenticate a partner store and issue a Bearer token.
     */
    public function login(
        PartnerPortalLoginRequest $request,
        TenantContext $tenantContext
    ): JsonResponse {
        $dto = $this->authService->orchestratePartnerLogin(
            $tenantContext->get(),
            $request->validated()
        );

        return response()->json($dto->toArray(), Response::HTTP_OK);
    }

    /**
     * Return the authenticated partner store profile and origin tenant summary.
     */
    public function me(
        Request $request,
        TenantContext $tenantContext
    ): JsonResponse {
        /** @var PartnerStore $partner */
        $partner = $request->user();
        $tenant = $tenantContext->get();

        return response()->json([
            'partner_store' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'portal_email' => $partner->portal_email,
                'benefit_title' => $partner->benefit_title,
            ],
            'origin_tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Revoke the current access token for this partner device.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var PartnerStore $partner */
        $partner = $request->user();
        $this->authService->revokeCurrentToken($partner);

        return response()->json([
            'message' => 'Sesión cerrada exitosamente en este dispositivo.',
        ], Response::HTTP_OK);
    }
}
