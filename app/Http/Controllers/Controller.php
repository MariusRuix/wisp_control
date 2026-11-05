<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Opciones del selector "Por página" en las listas. */
    public const OPCIONES_POR_PAGINA = [25, 50, 100];

    /** Registros por página pedidos en ?por_pagina= (solo valores permitidos). */
    protected function porPagina(Request $request, int $porDefecto = 25): int
    {
        $n = $request->integer('por_pagina', $porDefecto);

        return in_array($n, self::OPCIONES_POR_PAGINA, true) ? $n : $porDefecto;
    }
}
