<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan operador:crear "Juan Pérez" juan@empresa.mx
 */
final class CrearOperador extends Command
{
    protected $signature = 'operador:crear {nombre} {email}';

    protected $description = 'Crea un usuario operador para entrar al panel';

    public function handle(): int
    {
        $password = (string) $this->secret('Contraseña (mín. 10 caracteres)');

        if (mb_strlen($password) < 10) {
            $this->error('La contraseña debe tener al menos 10 caracteres.');

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => (string) $this->argument('email')],
            ['name' => (string) $this->argument('nombre'), 'password' => Hash::make($password)],
        );

        $this->info('Operador listo.');

        return self::SUCCESS;
    }
}
