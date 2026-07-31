<?php

/**
 * Sin este archivo, Laravel permite peticiones desde cualquier origen.
 * Eso deja que una web maliciosa llame a la API con el navegador de un
 * empleado logueado y se lleve datos de pacientes.
 *
 * Los orígenes permitidos salen de FRONTEND_URL (.env), separados por coma.
 */
$origenes = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
)));

return [

    // sanctum/csrf-cookie también necesita CORS: es el primer pedido del SPA.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origenes,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Obligatorio para la sesión por cookie: sin esto el navegador no envía ni
    // acepta las cookies de sesión y CSRF en peticiones cross-origin.
    // Por eso allowed_origins nunca puede ser '*': el navegador lo rechaza
    // cuando supports_credentials está activo.
    'supports_credentials' => true,

];
