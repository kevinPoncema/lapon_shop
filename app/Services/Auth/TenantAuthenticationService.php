<?php

namespace App\Services\Auth;

use App\Context\TenantContext;
use App\DTOs\IssuedTokenResultDTO;
use App\Exceptions\Domain\AccountSuspendedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\PartnerStore;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\PartnerStoreRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

class TenantAuthenticationService
{
    /**
     * Dummy bcrypt hash to prevent timing attacks when an account does not exist.
     */
    protected const DUMMY_HASH = '$2y$12$D41PqICaeCQzo/t40OV8aupAirPOZLThtErgEQk38q760B3cBDSmK';

    public function __construct(
        protected TenantContext $tenantContext,
        protected UserRepositoryInterface $userRepository,
        protected PartnerStoreRepositoryInterface $partnerStoreRepository
    ) {}

    /**
     * Sanea y normaliza correos electrónicos.
     */
    public function sanitizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Busca un usuario por correo acotado al contexto del inquilino activo.
     */
    public function findTenantUserByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($this->sanitizeEmail($email));
    }

    /**
     * Busca un comercio aliado por correo acotado al contexto del inquilino activo.
     */
    public function findTenantPartnerByEmail(string $portalEmail): ?PartnerStore
    {
        return $this->partnerStoreRepository->findByPortalEmail($this->sanitizeEmail($portalEmail));
    }

    /**
     * Verifica la contraseña en tiempo constante para mitigar ataques de temporización.
     */
    public function verifyPasswordConstantTime(?string $hashedPassword, string $plainPassword): bool
    {
        if ($hashedPassword === null || $hashedPassword === '') {
            Hash::check($plainPassword, self::DUMMY_HASH);

            return false;
        }

        return Hash::check($plainPassword, $hashedPassword);
    }

    /**
     * Valida que la cuenta se encuentre activa. Si está suspendida, purga tokens y aborta con 403.
     *
     * @throws AccountSuspendedException
     */
    public function assertAccountIsActive(bool $isActive, User|PartnerStore $actor): void
    {
        if (! $isActive) {
            $actor->tokens()->delete();

            throw new AccountSuspendedException;
        }
    }

    /**
     * Resuelve los alcances (abilities) según el rol administrativo del usuario.
     *
     * @return list<string>
     */
    public function resolveAdminAbilities(User $user): array
    {
        $role = strtoupper($user->role);

        return match ($role) {
            'STAFF' => ['tenant:staff'],
            default => ['tenant:admin'],
        };
    }

    /**
     * Calcula la fecha de expiración para tokens administrativos.
     */
    public function calculateAdminExpiration(): CarbonImmutable
    {
        $minutes = (int) config('sanctum.admin_expiration_minutes', 10080);

        return now()->toImmutable()->addMinutes($minutes);
    }

    /**
     * Calcula la fecha de expiración para tokens de comercios aliados.
     */
    public function calculatePartnerExpiration(): CarbonImmutable
    {
        $minutes = (int) config('sanctum.partner_expiration_minutes', 43200);

        return now()->toImmutable()->addMinutes($minutes);
    }

    /**
     * Emite un token polimórfico de Sanctum con abilities y fecha de expiración explícitas.
     *
     * @param  list<string>  $abilities
     */
    public function issueSanctumToken(
        User|PartnerStore $actor,
        string $deviceName,
        array $abilities,
        CarbonInterface $expiresAt
    ): NewAccessToken {
        return $actor->createToken($deviceName, $abilities, $expiresAt);
    }

    /**
     * Orquesta el inicio de sesión de un administrador / staff.
     *
     * @param  array{email: string, password: string, device_name?: string|null}  $credentials
     *
     * @throws InvalidCredentialsException
     * @throws AccountSuspendedException
     */
    public function orchestrateAdminLogin(Tenant $tenant, array $credentials): IssuedTokenResultDTO
    {
        $cleanEmail = $this->sanitizeEmail($credentials['email']);
        $user = $this->findTenantUserByEmail($cleanEmail);

        $passwordValid = $this->verifyPasswordConstantTime(
            $user?->password,
            $credentials['password']
        );

        if (! $user || ! $passwordValid) {
            throw new InvalidCredentialsException;
        }

        $this->assertAccountIsActive((bool) $user->is_active, $user);

        $abilities = $this->resolveAdminAbilities($user);
        $deviceName = ! empty($credentials['device_name']) ? (string) $credentials['device_name'] : 'admin-web';
        $expiresAt = $this->calculateAdminExpiration();

        $tokenResult = $this->issueSanctumToken($user, $deviceName, $abilities, $expiresAt);

        return new IssuedTokenResultDTO(
            accessToken: $tokenResult->plainTextToken,
            tokenType: 'Bearer',
            abilities: $abilities,
            expiresAt: $expiresAt->toISOString(),
            user: [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            tenant: [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ]
        );
    }

    /**
     * Orquesta el inicio de sesión de un comercio aliado.
     *
     * @param  array{email?: string|null, portal_email?: string|null, password?: string|null, portal_password?: string|null, device_name?: string|null}  $credentials
     *
     * @throws InvalidCredentialsException
     * @throws AccountSuspendedException
     */
    public function orchestratePartnerLogin(Tenant $tenant, array $credentials): IssuedTokenResultDTO
    {
        $rawEmail = (string) ($credentials['portal_email'] ?? $credentials['email'] ?? '');
        $rawPassword = (string) ($credentials['portal_password'] ?? $credentials['password'] ?? '');

        $cleanEmail = $this->sanitizeEmail($rawEmail);
        $partner = $this->findTenantPartnerByEmail($cleanEmail);

        $passwordValid = $this->verifyPasswordConstantTime(
            $partner?->portal_password,
            $rawPassword
        );

        if (! $partner || ! $passwordValid) {
            throw new InvalidCredentialsException;
        }

        $this->assertAccountIsActive((bool) $partner->is_active, $partner);

        $abilities = ['partner-portal'];
        $deviceName = ! empty($credentials['device_name']) ? (string) $credentials['device_name'] : 'partner-pos';
        $expiresAt = $this->calculatePartnerExpiration();

        $tokenResult = $this->issueSanctumToken($partner, $deviceName, $abilities, $expiresAt);

        return new IssuedTokenResultDTO(
            accessToken: $tokenResult->plainTextToken,
            tokenType: 'Bearer',
            abilities: $abilities,
            expiresAt: $expiresAt->toISOString(),
            partnerStore: [
                'id' => $partner->id,
                'name' => $partner->name,
                'portal_email' => $partner->portal_email,
                'benefit_title' => $partner->benefit_title,
            ],
            originTenant: [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ]
        );
    }

    /**
     * Revoca únicamente el token Bearer utilizado en la petición actual.
     */
    public function revokeCurrentToken(User|PartnerStore $actor): void
    {
        $actor->currentAccessToken()->delete();
        Auth::guard('sanctum')->forgetUser();
    }

    /**
     * Revoca todas las sesiones activas del sujeto autenticado en cualquier dispositivo.
     */
    public function revokeAllTokens(User|PartnerStore $actor): int
    {
        $deletedCount = (int) $actor->tokens()->delete();
        Auth::guard('sanctum')->forgetUser();

        return $deletedCount;
    }
}
