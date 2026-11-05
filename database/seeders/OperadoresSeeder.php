<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class OperadoresSeeder extends Seeder
{
    public const PASSWORD = 'password1234';

    public function run(): void
    {
        $operadores = [
            ['name' => 'Administrador', 'email' => 'admin@wisp.test', 'activo' => true, 'rol' => 'admin'],
            ['name' => 'Laura Cobranza', 'email' => 'laura@wisp.test', 'activo' => true, 'rol' => 'operador'],
            ['name' => 'Luis Técnico', 'email' => 'luis@wisp.test', 'activo' => true, 'rol' => 'operador'],
            ['name' => 'Pedro (ya no labora)', 'email' => 'pedro@wisp.test', 'activo' => false, 'rol' => 'operador'],
        ];

        foreach ($operadores as $o) {
            User::updateOrCreate(
                ['email' => $o['email']],
                [...$o, 'password' => self::PASSWORD, 'ultimo_acceso' => $o['activo'] ? now()->subHours(random_int(1, 72)) : now()->subMonths(2)],
            );
        }
    }
}
