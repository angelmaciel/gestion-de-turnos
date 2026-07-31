<?php

namespace Tests;

use App\Models\Room;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Contexto compartido que arma el beforeEach de cada archivo de tests.
     * Se declaran acá para que el análisis estático las conozca: en Pest se
     * asignan sobre $this dentro de closures y, sin declaración, PHPStan las
     * ve como accesos a propiedades inexistentes.
     */
    public ?Specialty $specialty = null;

    public ?Room $room = null;
}
