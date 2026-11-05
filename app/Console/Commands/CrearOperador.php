<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan operador:crear "Juan Pérez" juan@empresa.mx              -> Administrador
 * php artisan operador:crear "Ana López" ana@empresa.mx --rol=operador -> Cajero/Técnico
 */
final class CrearOperador extends Command
{
    protected $signature = 'operador:crear {nombre} {email} {--rol=admin : admin u operador}';

    protected $description = 'Crea un usuario operador para entrar al panel';

    public function handle(): int
    {
        $rol = Rol::tryFrom((string) $this->option('rol'));
        if ($rol === null) {
            $this->error('Rol inválido. Usa --rol=admin o --rol=operador.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña (mín. 10 caracteres)');

        if (mb_strlen($password) < 10) {
            $this->error('La contraseña debe tener al menos 10 caracteres.');

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => (string) $this->argument('email')],
            ['name' => (string) $this->argument('nombre'), 'password' => Hash::make($password), 'activo' => true, 'rol' => $rol],
        );

        $this->info("Operador listo: {$rol->etiqueta()}, activo. También sirve para recuperar el acceso si olvidaste tu contraseña.");

        return self::SUCCESS;
    }
}
