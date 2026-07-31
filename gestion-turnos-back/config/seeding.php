<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contraseña de los usuarios de prueba
    |--------------------------------------------------------------------------
    |
    | La usan los seeders de desarrollo. Vive en config y no en un env() suelto
    | porque fuera del directorio config env() devuelve null cuando la
    | configuración está cacheada (php artisan config:cache).
    |
    | Los seeders de usuarios no corren en producción.
    |
    */

    'password' => env('SEED_PASSWORD', 'password123'),

];
