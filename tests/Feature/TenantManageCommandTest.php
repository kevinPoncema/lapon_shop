<?php

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('artisan tenant:manage create-tenant creates tenant and empty config', function () {
    $this->artisan('tenant:manage', [
        'action' => 'create-tenant',
        '--name' => 'Boutique San Pedro',
        '--slug' => 'boutique-sp',
        '--country' => 'HN',
        '--currency' => 'HNL',
        '--active' => 'true',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('✔ Inquilino [Boutique San Pedro] creado exitosamente.');

    $tenant = Tenant::where('slug', 'boutique-sp')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Boutique San Pedro')
        ->and($tenant->country_code)->toBe('HN')
        ->and($tenant->currency_code)->toBe('HNL')
        ->and($tenant->is_active)->toBeTrue();

    $config = TenantConfig::find($tenant->id);
    expect($config)->not->toBeNull()
        ->and($config->branding)->toBe([])
        ->and($config->contact_info)->toBe([])
        ->and($config->shipping_zones)->toBe([])
        ->and($config->metadata_schema)->toBe([])
        ->and($config->filter_config)->toBe([])
        ->and($config->outbound_reward_rules)->toBe([]);
});

test('artisan tenant:manage create-tenant fails on duplicate slug', function () {
    Tenant::create([
        'name' => 'Original Store',
        'slug' => 'original-store',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $this->artisan('tenant:manage', [
        'action' => 'create-tenant',
        '--name' => 'Duplicate Store',
        '--slug' => 'original-store',
        '--country' => 'HN',
        '--currency' => 'HNL',
    ])
        ->assertFailed()
        ->expectsOutputToContain('Errores de validación:');
});

test('artisan tenant:manage list-tenants displays registered tenants', function () {
    $tenant = Tenant::create([
        'name' => 'Tienda Demo Listado',
        'slug' => 'demo-listado',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $this->artisan('tenant:manage', ['action' => 'list-tenants'])
        ->assertSuccessful()
        ->expectsOutputToContain('demo-listado');
});

test('artisan tenant:manage create-user registers admin user under tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Centro',
        'slug' => 'comercio-centro',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $this->artisan('tenant:manage', [
        'action' => 'create-user',
        '--tenant' => 'comercio-centro',
        '--name' => 'Administrador Uno',
        '--email' => 'admin@centro.hn',
        '--password' => 'secretPass123',
        '--role' => 'OWNER',
        '--active' => 'true',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('✔ Usuario administrador [admin@centro.hn] creado exitosamente');

    $user = User::withoutTenantScope()->where('email', 'admin@centro.hn')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->role)->toBe('OWNER')
        ->and(Hash::check('secretPass123', $user->password))->toBeTrue();
});

test('artisan tenant:manage list-users displays users for specific tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Norte',
        'slug' => 'comercio-norte',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Staff Norte',
        'email' => 'staff@norte.hn',
        'password' => 'password',
        'role' => 'STAFF',
    ]);

    $this->artisan('tenant:manage', [
        'action' => 'list-users',
        '--tenant' => 'comercio-norte',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('staff@norte.hn');
});

test('artisan tenant:manage toggle-user-status alternates active flag', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Sur',
        'slug' => 'comercio-sur',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Usuario Sur',
        'email' => 'user@sur.hn',
        'password' => 'password',
        'role' => 'STAFF',
        'is_active' => true,
    ]);

    $this->artisan('tenant:manage', [
        'action' => 'toggle-user-status',
        '--tenant' => 'comercio-sur',
        '--email' => 'user@sur.hn',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('INACTIVO');

    expect($user->refresh()->is_active)->toBeFalse();

    $this->artisan('tenant:manage', [
        'action' => 'toggle-user-status',
        '--tenant' => 'comercio-sur',
        '--email' => 'user@sur.hn',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('ACTIVO');

    expect($user->refresh()->is_active)->toBeTrue();
});

test('artisan tenant:manage reset-user-password changes user password', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Este',
        'slug' => 'comercio-este',
        'country_code' => 'HN',
        'currency_code' => 'HNL',
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Usuario Este',
        'email' => 'user@este.hn',
        'password' => 'oldPassword',
        'role' => 'STAFF',
    ]);

    $this->artisan('tenant:manage', [
        'action' => 'reset-user-password',
        '--tenant' => 'comercio-este',
        '--email' => 'user@este.hn',
        '--password' => 'newBrandNewPassword123',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('✔ Contraseña actualizada exitosamente');

    expect(Hash::check('newBrandNewPassword123', $user->refresh()->password))->toBeTrue();
});
