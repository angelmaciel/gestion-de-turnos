<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Habilita $this->authorize() en todos los controladores. Laravel 11+ ya no
    // lo incluye por defecto, y sin esto las llamadas a authorize() fallarían.
    use AuthorizesRequests;
}
