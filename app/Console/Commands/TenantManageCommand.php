<?php

namespace App\Console\Commands;

use App\Context\TenantContext;
use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantManageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:manage
        {action? : Acción a ejecutar (create-tenant, list-tenants, create-user, list-users, toggle-user-status, reset-user-password)}
        {--tenant= : Slug o UUID del inquilino objetivo}
        {--name= : Nombre comercial del inquilino o nombre del usuario}
        {--slug= : Slug único del inquilino para URLs y cabeceras}
        {--country=HN : Código de país de 2 letras (ISO 3166-1)}
        {--currency=HNL : Código de moneda de 3 letras (ISO 4217)}
        {--email= : Correo electrónico del usuario}
        {--password= : Contraseña para el usuario}
        {--role=OWNER : Rol del usuario (OWNER, STAFF)}
        {--active=true : Estado activo (true/false)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CLI de administración y gestión integral de inquilinos (Tenants) y usuarios administradores.';

    public function __construct(
        protected TenantContext $tenantContext
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        if (! empty($action)) {
            return $this->handleDirectAction((string) $action);
        }

        return $this->handleInteractiveMenu();
    }

    /**
     * Maneja el menú interactivo principal.
     */
    protected function handleInteractiveMenu(): int
    {
        $this->displayBanner();

        while (true) {
            $choice = $this->choice(
                'Selecciona una operación',
                [
                    '1' => 'Crear nuevo Inquilino (Tenant + Configuración inicial)',
                    '2' => 'Listar Inquilinos registrados',
                    '3' => 'Gestionar Usuarios Administradores de un Inquilino',
                    '4' => 'Salir',
                ],
                '1'
            );

            switch ($choice) {
                case '1':
                case 'Crear nuevo Inquilino (Tenant + Configuración inicial)':
                    $this->createTenantAction();
                    break;

                case '2':
                case 'Listar Inquilinos registrados':
                    $this->listTenantsAction();
                    break;

                case '3':
                case 'Gestionar Usuarios Administradores de un Inquilino':
                    $this->handleUserManagementMenu();
                    break;

                case '4':
                case 'Salir':
                    $this->info('Operación finalizada. ¡Hasta luego!');

                    return self::SUCCESS;
            }

            $this->newLine();
        }
    }

    /**
     * Despacha acciones directas pasadas como argumento CLI.
     */
    protected function handleDirectAction(string $action): int
    {
        return match ($action) {
            'create-tenant' => $this->createTenantAction(),
            'list-tenants' => $this->listTenantsAction(),
            'create-user' => $this->createUserAction(),
            'list-users' => $this->listUsersAction(),
            'toggle-user-status' => $this->toggleUserStatusAction(),
            'reset-user-password' => $this->resetUserPasswordAction(),
            default => $this->invalidAction($action),
        };
    }

 

    /**
     * Orquesta la creación de un nuevo inquilino coordinando los pasos auxiliares.
     */
    public function createTenantAction(): int
    {
        $this->info('--- Crear Nuevo Inquilino (Tenant) ---');
        $rawInput = $this->collectTenantInput();
        $validatedData = $this->validateTenantInput($rawInput);

        if ($validatedData === null) {
            return self::FAILURE;
        }

        try {
            $tenant = $this->persistTenantWithConfig($validatedData);
            $this->displayTenantSummary($tenant);
            $this->promptInitialUserCreation($tenant);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error al persistir el inquilino: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Paso Auxiliar 1: Recolecta los parámetros del inquilino desde opciones CLI o preguntas.
     *
     * @return array<string, mixed>
     */
    protected function collectTenantInput(): array
    {
        $name = $this->option('name') ?: $this->ask('Nombre comercial o razón social');
        $suggestedSlug = Str::slug((string) $name);
        $slug = $this->option('slug') ?: $this->ask('Slug único para URL / subdominio', $suggestedSlug);
        $countryCode = strtoupper((string) ($this->option('country') ?: $this->ask('Código de país (2 letras ISO)', 'HN')));
        $currencyCode = strtoupper((string) ($this->option('currency') ?: $this->ask('Código de moneda base (3 letras ISO)', 'HNL')));

        $isActiveOption = $this->option('active');
        $isActive = $isActiveOption !== null
            ? filter_var($isActiveOption, FILTER_VALIDATE_BOOLEAN)
            : $this->confirm('¿Activar inquilino de inmediato?', true);

        return [
            'name' => (string) $name,
            'slug' => (string) $slug,
            'country_code' => $countryCode,
            'currency_code' => $currencyCode,
            'is_active' => $isActive,
        ];
    }

    /**
     * Paso Auxiliar 2: Valida los datos recolectados del inquilino.
     *
     * @param  array<string, mixed>  $input
     * @return array{name: string, slug: string, country_code: string, currency_code: string, is_active: bool}|null
     */
    protected function validateTenantInput(array $input): ?array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:tenants,slug'],
            'country_code' => ['required', 'string', 'size:2'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            $this->error('Errores de validación:');
            foreach ($validator->errors()->all() as $error) {
                $this->line(" - <fg=red>{$error}</>");
            }

            return null;
        }

        return [
            'name' => (string) $input['name'],
            'slug' => (string) $input['slug'],
            'country_code' => (string) $input['country_code'],
            'currency_code' => (string) $input['currency_code'],
            'is_active' => (bool) $input['is_active'],
        ];
    }

    /**
     * Paso Auxiliar 3: Persiste transaccionalmente el Tenant y su TenantConfig vacío.
     *
     * @param  array{name: string, slug: string, country_code: string, currency_code: string, is_active: bool}  $data
     */
    protected function persistTenantWithConfig(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'country_code' => $data['country_code'],
                'currency_code' => $data['currency_code'],
                'is_active' => $data['is_active'],
            ]);

            TenantConfig::create([
                'tenant_id' => $tenant->id,
                'branding' => [],
                'contact_info' => [],
                'shipping_zones' => [],
                'metadata_schema' => [],
                'filter_config' => [],
                'outbound_reward_rules' => [],
            ]);

            return $tenant;
        });
    }

    /**
     * Paso Auxiliar 4: Muestra el resumen visual del inquilino registrado.
     */
    protected function displayTenantSummary(Tenant $tenant): void
    {
        $this->info("✔ Inquilino [{$tenant->name}] creado exitosamente.");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID (UUID)', $tenant->id],
                ['Nombre', $tenant->name],
                ['Slug', $tenant->slug],
                ['País', $tenant->country_code],
                ['Moneda', $tenant->currency_code],
                ['Estado', $tenant->is_active ? 'Activo' : 'Inactivo'],
                ['Configuración', 'Inicializada (Vacía)'],
            ]
        );
    }

    /**
     * Paso Auxiliar 5: Orquesta la creación del primer usuario admin en modo interactivo.
     */
    protected function promptInitialUserCreation(Tenant $tenant): void
    {
        if (empty($this->argument('action')) && $this->confirm('¿Deseas crear el primer usuario administrador (OWNER) ahora?', true)) {
            $this->createUserForTenant($tenant);
        }
    }

    /**
     * Lista todos los inquilinos registrados en el sistema.
     */
    public function listTenantsAction(): int
    {
        $tenants = Tenant::withCount('users')->latest()->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay inquilinos registrados en la base de datos.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID (UUID)', 'Nombre', 'Slug', 'País', 'Moneda', 'Estado', 'Usuarios Admin', 'Creado el'],
            $tenants->map(fn (Tenant $t) => [
                $t->id,
                $t->name,
                $t->slug,
                $t->country_code,
                $t->currency_code,
                $t->is_active ? '<fg=green>Activo</>' : '<fg=red>Inactivo</>',
                $t->users_count,
                $t->created_at?->format('Y-m-d H:i') ?? 'N/A',
            ])->toArray()
        );

        return self::SUCCESS;
    }


    /**
     * Submenú interactivo para gestionar usuarios de un inquilino.
     */
    protected function handleUserManagementMenu(): void
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return;
        }

        $this->info("Gestionando usuarios del inquilino: [{$tenant->name}] ({$tenant->slug})");

        while (true) {
            $choice = $this->choice(
                "Acciones para [{$tenant->name}]",
                [
                    '1' => 'Listar usuarios administradores',
                    '2' => 'Crear nuevo usuario administrador',
                    '3' => 'Alternar estado activo/inactivo de un usuario',
                    '4' => 'Restablecer contraseña de un usuario',
                    '5' => 'Volver al menú principal',
                ],
                '1'
            );

            switch ($choice) {
                case '1':
                case 'Listar usuarios administradores':
                    $this->listUsersForTenant($tenant);
                    break;

                case '2':
                case 'Crear nuevo usuario administrador':
                    $this->createUserForTenant($tenant);
                    break;

                case '3':
                case 'Alternar estado activo/inactivo de un usuario':
                    $this->toggleUserStatusForTenant($tenant);
                    break;

                case '4':
                case 'Restablecer contraseña de un usuario':
                    $this->resetPasswordForTenant($tenant);
                    break;

                case '5':
                case 'Volver al menú principal':
                    return;
            }

            $this->newLine();
        }
    }

    /**
     * Crea un usuario administrador por CLI directo.
     */
    public function createUserAction(): int
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            $this->error('Debes especificar un inquilino válido mediante la opción --tenant={slug|id}.');

            return self::FAILURE;
        }

        return $this->createUserForTenant($tenant);
    }

    /**
     * Orquesta la creación de un usuario administrador para un inquilino.
     */
    protected function createUserForTenant(Tenant $tenant): int
    {
        $rawInput = $this->collectUserInput();
        $validatedData = $this->validateUserInput($tenant, $rawInput);

        if ($validatedData === null) {
            return self::FAILURE;
        }

        try {
            $user = $this->persistUserInTenant($tenant, $validatedData);
            $this->displayUserSummary($tenant, $user);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error al persistir usuario: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Paso Auxiliar 1: Recolecta los parámetros del usuario desde opciones CLI o preguntas.
     *
     * @return array<string, mixed>
     */
    protected function collectUserInput(): array
    {
        $name = $this->option('name') ?: $this->ask('Nombre completo del usuario');
        $email = $this->option('email') ?: $this->ask('Correo electrónico de acceso');
        $password = $this->option('password') ?: $this->secret('Contraseña (mínimo 8 caracteres)');

        $roleOption = $this->option('role');
        if (! empty($roleOption)) {
            $role = strtoupper((string) $roleOption);
        } else {
            $chosenRole = $this->choice('Rol administrativo', ['OWNER', 'STAFF'], 0);
            $role = strtoupper(is_array($chosenRole) ? (string) ($chosenRole[0] ?? 'OWNER') : (string) $chosenRole);
        }

        $isActiveOption = $this->option('active');
        $isActive = $isActiveOption !== null
            ? filter_var($isActiveOption, FILTER_VALIDATE_BOOLEAN)
            : $this->confirm('¿Habilitar acceso inmediato para este usuario?', true);

        return [
            'name' => (string) $name,
            'email' => (string) $email,
            'password' => (string) $password,
            'role' => $role,
            'is_active' => $isActive,
        ];
    }

    /**
     * Paso Auxiliar 2: Valida los datos del usuario con unicidad por inquilino.
     *
     * @param  array<string, mixed>  $input
     * @return array{name: string, email: string, password: string, role: string, is_active: bool}|null
     */
    protected function validateUserInput(Tenant $tenant, array $input): ?array
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->where('tenant_id', $tenant->id),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['OWNER', 'STAFF'])],
        ]);

        if ($validator->fails()) {
            $this->error('Errores al crear usuario:');
            foreach ($validator->errors()->all() as $error) {
                $this->line(" - <fg=red>{$error}</>");
            }

            return null;
        }

        return [
            'name' => (string) $input['name'],
            'email' => (string) $input['email'],
            'password' => (string) $input['password'],
            'role' => (string) $input['role'],
            'is_active' => (bool) $input['is_active'],
        ];
    }

    /**
     * Paso Auxiliar 3: Persiste el usuario dentro del contexto seguro del inquilino.
     *
     * @param  array{name: string, email: string, password: string, role: string, is_active: bool}  $data
     */
    protected function persistUserInTenant(Tenant $tenant, array $data): User
    {
        return $this->tenantContext->executeAs($tenant, function () use ($tenant, $data): User {
            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'is_active' => $data['is_active'],
                'email_verified_at' => now(),
            ]);
        });
    }

    /**
     * Paso Auxiliar 4: Muestra el resumen visual del usuario creado.
     */
    protected function displayUserSummary(Tenant $tenant, User $user): void
    {
        $this->info("✔ Usuario administrador [{$user->email}] creado exitosamente para el inquilino [{$tenant->name}].");
        $this->table(
            ['ID (UUID)', 'Inquilino', 'Nombre', 'Email', 'Rol', 'Estado'],
            [
                [$user->id, $tenant->slug, $user->name, $user->email, $user->role, $user->is_active ? 'Activo' : 'Inactivo'],
            ]
        );
    }

    /**
     * Lista los usuarios administradores de un inquilino por CLI directo.
     */
    public function listUsersAction(): int
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            $this->error('Debes especificar un inquilino válido mediante la opción --tenant={slug|id}.');

            return self::FAILURE;
        }

        return $this->listUsersForTenant($tenant);
    }

    /**
     * Lista los usuarios de un inquilino.
     */
    protected function listUsersForTenant(Tenant $tenant): int
    {
        $users = $this->tenantContext->executeAs($tenant, fn () => User::latest()->get());

        if ($users->isEmpty()) {
            $this->warn("No hay usuarios administradores registrados para el inquilino [{$tenant->name}].");

            return self::SUCCESS;
        }

        $this->table(
            ['ID (UUID)', 'Nombre', 'Email', 'Rol', 'Estado', 'Verificado', 'Alta'],
            $users->map(fn (User $u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->role === 'OWNER' ? '<fg=cyan>OWNER</>' : 'STAFF',
                $u->is_active ? '<fg=green>Activo</>' : '<fg=red>Inactivo</>',
                $u->email_verified_at ? 'Sí' : 'No',
                $u->created_at?->format('Y-m-d H:i') ?? 'N/A',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    /**
     * Alterna el estado activo de un usuario por CLI directo.
     */
    public function toggleUserStatusAction(): int
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            $this->error('Debes especificar un inquilino válido mediante --tenant.');

            return self::FAILURE;
        }

        return $this->toggleUserStatusForTenant($tenant);
    }

    /**
     * Alterna el estado activo de un usuario.
     */
    protected function toggleUserStatusForTenant(Tenant $tenant): int
    {
        $email = $this->option('email') ?: $this->ask('Ingresa el correo electrónico del usuario a modificar');

        /** @var User|null $user */
        $user = $this->tenantContext->executeAs(
            $tenant,
            fn () => User::where('email', (string) $email)->first()
        );

        if (! $user) {
            $this->error("Usuario con email [{$email}] no encontrado en el inquilino [{$tenant->name}].");

            return self::FAILURE;
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? '<fg=green>ACTIVO</>' : '<fg=red>INACTIVO</>';
        $this->line("✔ Estado del usuario [{$user->email}] actualizado a: {$statusText}.");

        return self::SUCCESS;
    }

    /**
     * Restablece la contraseña de un usuario por CLI directo.
     */
    public function resetUserPasswordAction(): int
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            $this->error('Debes especificar un inquilino válido mediante --tenant.');

            return self::FAILURE;
        }

        return $this->resetPasswordForTenant($tenant);
    }

    /**
     * Restablece la contraseña de un usuario.
     */
    protected function resetPasswordForTenant(Tenant $tenant): int
    {
        $email = $this->option('email') ?: $this->ask('Ingresa el correo del usuario a restablecer');

        /** @var User|null $user */
        $user = $this->tenantContext->executeAs(
            $tenant,
            fn () => User::where('email', (string) $email)->first()
        );

        if (! $user) {
            $this->error("Usuario con email [{$email}] no encontrado en el inquilino [{$tenant->name}].");

            return self::FAILURE;
        }

        $newPassword = $this->option('password') ?: $this->secret('Nueva contraseña (mínimo 8 caracteres)');

        if (strlen((string) $newPassword) < 8) {
            $this->error('La contraseña debe contener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user->password = Hash::make((string) $newPassword);
        $user->save();

        $this->info("✔ Contraseña actualizada exitosamente para el usuario [{$user->email}].");

        return self::SUCCESS;
    }


    /**
     * Resuelve el tenant activo a partir de la opción --tenant o solicitando selección.
     */
    protected function resolveTenant(): ?Tenant
    {
        $tenantInput = $this->option('tenant');

        if (! empty($tenantInput)) {
            $query = Tenant::where('slug', (string) $tenantInput);

            if (Str::isUuid((string) $tenantInput)) {
                $query->orWhere('id', (string) $tenantInput);
            }

            $tenant = $query->first();

            if (! $tenant) {
                $this->error("No se encontró ningún inquilino con slug o ID [{$tenantInput}].");

                return null;
            }

            return $tenant;
        }

        $tenants = Tenant::orderBy('name')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hay inquilinos registrados para seleccionar.');

            return null;
        }

        $options = $tenants->mapWithKeys(fn (Tenant $t) => [$t->slug => "{$t->name} ({$t->slug})"])->toArray();
        $selectedSlug = $this->choice('Selecciona el inquilino', $options);

        return $tenants->firstWhere('slug', $selectedSlug);
    }

    /**
     * Maneja una acción CLI desconocida.
     */
    protected function invalidAction(string $action): int
    {
        $this->error("Acción [{$action}] no reconocida.");
        $this->line('Acciones disponibles:');
        $this->line(' - <fg=yellow>create-tenant</>: Crea un inquilino y su tenant_config vacío.');
        $this->line(' - <fg=yellow>list-tenants</>: Lista todos los inquilinos.');
        $this->line(' - <fg=yellow>create-user</>: Crea un usuario administrador para un inquilino.');
        $this->line(' - <fg=yellow>list-users</>: Lista usuarios administradores de un inquilino.');
        $this->line(' - <fg=yellow>toggle-user-status</>: Alterna el estado activo/inactivo de un usuario.');
        $this->line(' - <fg=yellow>reset-user-password</>: Restablece la contraseña de un usuario.');

        return self::FAILURE;
    }

    /**
     * Muestra el encabezado visual en el terminal.
     */
    protected function displayBanner(): void
    {
        $this->line('====================================================');
        $this->line('  SaaS Multi-Tenant Management CLI - Commerce Hub   ');
        $this->line('====================================================');
    }
}
