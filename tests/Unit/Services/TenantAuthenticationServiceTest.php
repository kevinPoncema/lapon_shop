<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TenantAuthenticationService;

test('mapeo de roles administrativos asigna abilities tenant:admin para ADMIN y OWNER, y tenant:staff para STAFF', function () {
    $service = app(TenantAuthenticationService::class);

    $tenant = Tenant::factory()->create();

    $owner = new User(['tenant_id' => $tenant->id, 'role' => 'OWNER']);
    $admin = new User(['tenant_id' => $tenant->id, 'role' => 'ADMIN']);
    $staff = new User(['tenant_id' => $tenant->id, 'role' => 'STAFF']);

    expect($service->resolveAdminAbilities($owner))->toBe(['tenant:admin'])
        ->and($service->resolveAdminAbilities($admin))->toBe(['tenant:admin'])
        ->and($service->resolveAdminAbilities($staff))->toBe(['tenant:staff']);
});

test('calculo de expiracion diferencia correctamente los dias de vigencia de Admin y Partner Portal', function () {
    $service = app(TenantAuthenticationService::class);

    $adminExpiration = $service->calculateAdminExpiration();
    $partnerExpiration = $service->calculatePartnerExpiration();

    // Por defecto: 7 días (10080 mins) para Admin y 30 días (43200 mins) para Partner
    expect($adminExpiration->diffInDays(now()))->toBe(7)
        ->and($partnerExpiration->diffInDays(now()))->toBe(30);
});

test('saneamiento de correo electronico normaliza mayusculas y elimina espacios laterales', function () {
    $service = app(TenantAuthenticationService::class);

    expect($service->sanitizeEmail('  ADMIN@Ejemplo.COM  '))->toBe('admin@ejemplo.com')
        ->and($service->sanitizeEmail('CAJA@aliado.hn'))->toBe('caja@aliado.hn');
});
