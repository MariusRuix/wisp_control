<?php

declare(strict_types=1);

use App\Http\Controllers\AccionServicioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OnuController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/clientes');
    Route::get('/clientes', [OnuController::class, 'index'])->name('onus.index');
    Route::post('/acciones', [AccionServicioController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('acciones.store');
    Route::get('/bitacora', [AccionServicioController::class, 'index'])->name('bitacora.index');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
