<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de PRUEBA para local / capacitación.
 *
 *   php artisan migrate:fresh --seed     (borra todo y vuelve a llenar)
 *   php artisan db:seed                  (agrega encima; los operadores no se duplican)
 *
 * Nunca corre en producción. Las ONUs de prueba usan IDs "DEMO-…" que no
 * existen en SmartOLT, así que un corte sobre ellas falla sin afectar a nadie.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los seeders de prueba no se ejecutan en producción (APP_ENV=production).');
        }

        $reales = \App\Models\Cliente::withTrashed()->where(fn ($q) => $q->whereNull('numero')->orWhere('numero', 'not like', 'DEMO-%'))->count();
        if ($reales > 0) {
            $this->call(OperadoresSeeder::class);
            $this->command?->warn("Hay {$reales} cliente(s) que no son de prueba: no agrego datos demo para no mezclarlos (ni generarles cargos).");
            $this->command?->warn('Usa una base de datos de prueba y: php artisan migrate:fresh --seed');

            return;
        }

        if (\App\Models\Cliente::withTrashed()->where('numero', 'like', 'DEMO-%')->exists()) {
            $this->call(OperadoresSeeder::class);
            $this->command?->warn('Ya hay datos de prueba. Para empezar de cero: php artisan migrate:fresh --seed');

            return;
        }

        $this->call([
            OperadoresSeeder::class,
            OnusDemoSeeder::class,
            ClientesDemoSeeder::class,
            CobrosDemoSeeder::class,
            CortesDemoSeeder::class,
            FallasDemoSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Listo. Administrador: admin@wisp.test · Cajero/Técnico: laura@wisp.test (contraseña: password1234)');
        $this->command?->line('Revisa a quién cortaría: php artisan cortes:automaticos --prueba');
    }
}
