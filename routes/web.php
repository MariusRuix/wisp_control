<?php

declare(strict_types=1);

use App\Http\Controllers\AccionServicioController;
use App\Http\Controllers\AdeudoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\FallaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\OnuController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', InicioController::class)->name('inicio');

    // Clientes (altas, edición, ficha, baja)
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])
        ->whereNumber('cliente')->withTrashed()->name('clientes.show');
    Route::resource('clientes', ClienteController::class)->except('show');
    Route::post('/clientes/{id}/reactivar', [ClienteController::class, 'restore'])
        ->whereNumber('id')->name('clientes.restore');

    // Cobros
    Route::post('/clientes/{cliente}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::post('/pagos/{pago}/cancelar', [PagoController::class, 'cancelar'])->name('pagos.cancelar');
    Route::post('/clientes/{cliente}/cargos', [CargoController::class, 'store'])->name('cargos.store');
    Route::post('/cargos/{cargo}/cancelar', [CargoController::class, 'cancelar'])->name('cargos.cancelar');
    Route::get('/cobros/adeudos', [AdeudoController::class, 'index'])->name('cobros.adeudos');
    Route::get('/cobros/pagos', [PagoController::class, 'index'])->name('cobros.pagos');

    // Fallas
    Route::get('/fallas', [FallaController::class, 'index'])->name('fallas.index');
    Route::post('/clientes/{cliente}/fallas', [FallaController::class, 'store'])->name('fallas.store');
    Route::get('/fallas/{falla}/editar', [FallaController::class, 'edit'])->name('fallas.edit');
    Route::put('/fallas/{falla}', [FallaController::class, 'update'])->name('fallas.update');

    // Operadores (solo Administrador)
    Route::middleware('can:administrar-usuarios')->group(function (): void {
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'usuario']);
        Route::post('/usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar'])->name('usuarios.desactivar');
        Route::post('/usuarios/{usuario}/activar', [UsuarioController::class, 'activar'])->name('usuarios.activar');
    });
    Route::get('/mi-contrasena', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/mi-contrasena', [PasswordController::class, 'update'])->name('password.update');

    // Configuración (solo Administrador)
    Route::middleware('can:configurar-sistema')->prefix('configuracion')->name('configuracion.')->group(function (): void {
        Route::get('/smartolt', [ConfiguracionController::class, 'smartolt'])->name('smartolt');
        Route::put('/smartolt', [ConfiguracionController::class, 'guardar'])->name('smartolt.guardar');
        Route::post('/smartolt/probar', [ConfiguracionController::class, 'probar'])->middleware('throttle:10,1')->name('smartolt.probar');
        Route::post('/smartolt/sincronizar', [ConfiguracionController::class, 'sincronizar'])->middleware('throttle:5,1')->name('smartolt.sincronizar');
        Route::post('/smartolt/restablecer', [ConfiguracionController::class, 'restablecer'])->name('smartolt.restablecer');
    });

    // ONUs sincronizadas de SmartOLT
    Route::get('/onus', [OnuController::class, 'index'])->name('onus.index');

    Route::post('/acciones', [AccionServicioController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('acciones.store');
    Route::get('/bitacora', [AccionServicioController::class, 'index'])->name('bitacora.index');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
