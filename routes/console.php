<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Cambios de clientes/ONUs desde SmartOLT
Schedule::command('smartolt:sincronizar --cambios')->everyThirtyMinutes()->withoutOverlapping();

// Estado de red (SmartOLT recomienda cada 5-7 min si no usas webhooks)
Schedule::command('smartolt:sincronizar --estados')->cron('*/6 * * * *')->withoutOverlapping();

// Mensualidades del mes (idempotente: se puede correr todos los días)
Schedule::command('cobros:generar-cargos')->dailyAt('00:10')->withoutOverlapping();

// Corte automático por adeudo (solo si COBROS_CORTE_AUTOMATICO=true)
Schedule::command('cortes:automaticos')
    ->dailyAt((string) config('cobros.hora_corte', '09:00'))
    ->when(fn (): bool => (bool) config('cobros.corte_automatico'))
    ->withoutOverlapping();

// Sincronización completa una vez al día
Schedule::command('smartolt:sincronizar')->dailyAt('03:15')->withoutOverlapping();
