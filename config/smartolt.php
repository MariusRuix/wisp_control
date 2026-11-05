<?php

declare(strict_types=1);

return [
    /*
    | Subdominio de tu cuenta: https://{subdominio}.smartolt.com
    */
    'subdomain' => env('SMARTOLT_SUBDOMAIN'),

    /*
    | API key (Settings > API KEYS en SmartOLT). Se envía en el header X-Token.
    | Nunca la pongas en el código ni en el frontend.
    */
    'token' => env('SMARTOLT_TOKEN'),

    'timeout' => (int) env('SMARTOLT_TIMEOUT', 30),

    /*
    | SmartOLT acepta hasta 50 ONUs por llamada bulk sin async=1.
    */
    'bulk_chunk' => 50,

    /*
    | Rutas de la API. Están aquí para que puedas ajustarlas sin tocar el
    | servicio si SmartOLT cambia algo. {id} = unique_external_id de la ONU.
    */
    'endpoints' => [
        'disable' => '/api/onu/disable/{id}',
        'enable' => '/api/onu/enable/{id}',
        'bulk_disable' => '/api/onu/bulk_disable',
        'bulk_enable' => '/api/onu/bulk_enable',
        'all_details' => '/api/onu/get_all_onus_details',
        'all_statuses' => '/api/onu/get_onus_statuses',
        // Llamada ligera para "Probar conexión" en Configuración
        'prueba' => '/api/system/get_olts',
    ],

    /*
    | Nombre del campo que reciben bulk_enable / bulk_disable.
    */
    'bulk_field' => env('SMARTOLT_BULK_FIELD', 'onus_external_ids'),
];
