<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    RateLimiter::clear('tenant-auth');

    $this->tenantA = Tenant::factory()->create([
        'name' => 'Tienda Alpha',
        'slug' => 'tienda-alpha',
        'is_active' => true,
    ]);

    $this->tenantB = Tenant::factory()->create([
        'name' => 'Tienda Beta',
        'slug' => 'tienda-beta',
        'is_active' => true,
    ]);

    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Ana Administradora',
        'email' => 'ana@alpha.hn',
        'password' => Hash::make('ClaveSegura123!'),
        'role' => 'ADMIN',
        'is_active' => true,
    ]);

    $this->staffA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Samuel Staff',
        'email' => 'samuel@alpha.hn',
        'password' => Hash::make('StaffSecret2026!'),
        'role' => 'STAFF',
        'is_active' => true,
    ]);
});

test('administrador inicia sesion con credenciales validas recibiendo token Bearer con ability tenant:admin y expiracion', function () {
    $payload = [
        'email' => 'ana@alpha.hn',
        'password' => 'ClaveSegura123!',
        'device_name' => 'Chrome Linux Desktop',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', $payload);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'abilities',
            'expires_at',
            'user' => ['id', 'name', 'email', 'role'],
            'tenant' => ['id', 'name', 'slug'],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('abilities', ['tenant:admin'])
        ->assertJsonPath('user.email', 'ana@alpha.hn')
        ->assertJsonPath('user.role', 'ADMIN')
        ->assertJsonPath('tenant.slug', 'tienda-alpha');

    expect($response->json('access_token'))->not->toBeEmpty()
        ->and($response->json('expires_at'))->not->toBeNull();
});

test('usuario con rol STAFF recibe token Bearer con ability tenant:staff', function () {
    $payload = [
        'email' => 'samuel@alpha.hn',
        'password' => 'StaffSecret2026!',
    ];

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', $payload);

    $response->assertOk()
        ->assertJsonPath('abilities', ['tenant:staff'])
        ->assertJsonPath('user.role', 'STAFF');
});

test('endpoint me retorna el perfil del usuario autenticado sin exponer password ni secretos 2FA', function () {
    $tokenResult = $this->adminA->createToken('test-device', ['tenant:admin']);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'tienda-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/admin/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.id', $this->adminA->id)
        ->assertJsonPath('user.email', 'ana@alpha.hn')
        ->assertJsonPath('tenant.slug', 'tienda-alpha')
        ->assertJsonMissing([
            'password',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'remember_token',
        ]);
});

test('logout revoca unicamente el token actual manteniendo vivos los tokens de otros dispositivos del usuario', function () {
    $tokenDevice1 = $this->adminA->createToken('laptop', ['tenant:admin']);
    $tokenDevice2 = $this->adminA->createToken('telefono', ['tenant:admin']);

    expect($this->adminA->tokens()->count())->toBe(2);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'tienda-alpha',
        'Authorization' => 'Bearer '.$tokenDevice1->plainTextToken,
    ])->postJson('/api/v1/admin/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente en este dispositivo.');

    $remainingTokens = $this->adminA->fresh()->tokens;
    expect($remainingTokens->count())->toBe(1)
        ->and($remainingTokens->first()->id)->toBe($tokenDevice2->accessToken->id);
});

test('logout-all revoca todas las sesiones activas del usuario', function () {
    $this->adminA->createToken('device-1', ['tenant:admin']);
    $this->adminA->createToken('device-2', ['tenant:admin']);
    $token3 = $this->adminA->createToken('device-3', ['tenant:admin']);

    expect($this->adminA->tokens()->count())->toBe(3);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'tienda-alpha',
        'Authorization' => 'Bearer '.$token3->plainTextToken,
    ])->postJson('/api/v1/admin/auth/logout-all');

    $response->assertOk()
        ->assertJsonPath('message', 'Se han cerrado todas las sesiones activas.');

    expect($this->adminA->fresh()->tokens()->count())->toBe(0);
});

test('login con contrasena incorrecta o correo inexistente retorna 401 INVALID_CREDENTIALS con estructura identica', function () {
    // 1. Correo que no existe
    $responseUnknown = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'noexiste@alpha.hn',
            'password' => 'CualquierClave123!',
        ]);

    $responseUnknown->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
        ->assertJsonPath('error.message', 'Las credenciales proporcionadas son incorrectas.');

    // 2. Correo existente pero contraseña incorrecta
    $responseWrongPass = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'ana@alpha.hn',
            'password' => 'ClaveEquivocada999!',
        ]);

    $responseWrongPass->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
        ->assertJsonPath('error.message', 'Las credenciales proporcionadas son incorrectas.');
});

test('credenciales validas del Tenant A enviadas con X-Tenant-Slug del Tenant B retornan 401 INVALID_CREDENTIALS', function () {
    $response = $this->withHeader('X-Tenant-Slug', 'tienda-beta')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'ana@alpha.hn',
            'password' => 'ClaveSegura123!',
        ]);

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

test('dos usuarios con el mismo email en Tenant A y Tenant B inician sesion cada uno en su respectivo tenant sin colision', function () {
    $userInTenantB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Ana Beta',
        'email' => 'ana@alpha.hn', // mismo correo
        'password' => Hash::make('ClaveTenantB999!'),
        'role' => 'ADMIN',
        'is_active' => true,
    ]);

    // Login en Tenant A con su clave
    $resA = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'ana@alpha.hn',
            'password' => 'ClaveSegura123!',
        ]);
    $resA->assertOk()->assertJsonPath('tenant.slug', 'tienda-alpha');

    // Login en Tenant B con su clave
    $resB = $this->withHeader('X-Tenant-Slug', 'tienda-beta')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'ana@alpha.hn',
            'password' => 'ClaveTenantB999!',
        ]);
    $resB->assertOk()->assertJsonPath('tenant.slug', 'tienda-beta');
});

test('usuario con is_active false recibe 403 ACCOUNT_SUSPENDED al intentar iniciar sesion', function () {
    $this->adminA->update(['is_active' => false]);

    $response = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'ana@alpha.hn',
            'password' => 'ClaveSegura123!',
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
});

test('token emitido para Tenant A usado posteriormente contra rutas con X-Tenant-Slug del Tenant B recibe 403 CROSS_TENANT_ACCESS_DENIED', function () {
    $tokenResult = $this->adminA->createToken('device-a', ['tenant:admin']);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'tienda-beta', // Inquilino B
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/admin/auth/me');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'CROSS_TENANT_ACCESS_DENIED');
});

test('superar el limite de intentos fallidos dispara 429 Too Many Requests aislado por tenant e IP', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
            ->postJson('/api/v1/admin/auth/login', [
                'email' => 'brute@alpha.hn',
                'password' => 'WrongPass!',
            ])
            ->assertStatus(401);
    }

    // 6to intento debe recibir 429
    $responseBlocked = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'brute@alpha.hn',
            'password' => 'WrongPass!',
        ]);

    $responseBlocked->assertStatus(429);

    // Otro email en el mismo tenant no está bloqueado
    $responseOther = $this->withHeader('X-Tenant-Slug', 'tienda-alpha')
        ->postJson('/api/v1/admin/auth/login', [
            'email' => 'otro@alpha.hn',
            'password' => 'WrongPass!',
        ]);

    $responseOther->assertStatus(401);
});

test('token cuyo expires_at ya vencio es rechazado con 401 Unauthorized', function () {
    $tokenResult = $this->adminA->createToken('expired-device', ['tenant:admin']);

    // Forzar expiración en base de datos
    PersonalAccessToken::findToken($tokenResult->plainTextToken)
        ->update(['expires_at' => now()->subMinute()]);

    $response = $this->withHeaders([
        'X-Tenant-Slug' => 'tienda-alpha',
        'Authorization' => 'Bearer '.$tokenResult->plainTextToken,
    ])->getJson('/api/v1/admin/auth/me');

    $response->assertStatus(401);
});
