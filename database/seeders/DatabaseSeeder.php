<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DemoTenantSeeder::class,
            // Descomenta la siguiente línea para poblar miles de productos de estrés:
            // DemoCatalogStressSeeder::class,
        ]);

        // O activa la variable de entorno: SEED_STRESS_CATALOG=true
        if ((bool) config('app.seed_stress_catalog', false)) {
            $this->call(DemoCatalogStressSeeder::class);
        }
    }
}
