<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Cambios de clientes/ONUs desde SmartOLT
Schedule::command('smartolt:sincronizar --cambios')->everyThirtyMinutes()->withoutOverlapping();

// Estado de red (SmartOLT recomienda cada 5-7 min si no usas webhooks)
Schedule::command('smartolt:sincronizar --estados')->cron('*/6 * * * *')->withoutOverlapping();

// Sincronización completa una vez al día
Schedule::command('smartolt:sincronizar')->dailyAt('03:15')->withoutOverlapping();
