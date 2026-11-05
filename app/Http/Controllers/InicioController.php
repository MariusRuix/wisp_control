<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ResumenInicio;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InicioController extends Controller
{
    public function __invoke(Request $request, ResumenInicio $resumen): View
    {
        return view('inicio', ['r' => $resumen->calcular($request->user())]);
    }
}
