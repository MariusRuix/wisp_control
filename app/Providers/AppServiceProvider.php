<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.wisp');
        Paginator::defaultSimpleView('vendor.pagination.wisp');

        // Permisos del rol Administrador. El rol Cajero/Técnico puede todo lo demás:
        // clientes, registrar pagos y cargos, fallas y cortar/reconectar de uno en uno.
        Gate::define('administrar-usuarios', fn (User $u): bool => $u->esAdmin());
        Gate::define('cancelar-movimientos', fn (User $u): bool => $u->esAdmin());
        Gate::define('cortar-masivo', fn (User $u): bool => $u->esAdmin());
        Gate::define('configurar-sistema', fn (User $u): bool => $u->esAdmin());
    }
}
