<?php

declare(strict_types=1);

return [
    /*
    | Días después del vencimiento antes de cortar automáticamente.
    | Con 3: si vence el día 5, se corta a partir del día 9.
    */
    'dias_gracia' => (int) env('COBROS_DIAS_GRACIA', 3),

    /*
    | Interruptor general del corte automático. Apagado por defecto: actívalo
    | (COBROS_CORTE_AUTOMATICO=true) solo después de probar con una ONU real
    | y revisar `php artisan cortes:automaticos --prueba`.
    */
    'corte_automatico' => (bool) env('COBROS_CORTE_AUTOMATICO', false),

    /* Hora del corte diario (24 h, zona APP_TIMEZONE). */
    'hora_corte' => env('COBROS_HORA_CORTE', '09:00'),

    /*
    | Reconectar sola a la persona cuando paga todo lo vencido.
    | Solo aplica si su último corte fue automático: un corte manual
    | (por ejemplo por mal uso) nunca se revierte solo.
    */
    'reconexion_automatica' => (bool) env('COBROS_RECONEXION_AUTOMATICA', true),

    'moneda' => 'MXN',
];
