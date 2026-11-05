<?php

declare(strict_types=1);

namespace Database\Seeders;

/** Nombres y domicilios ficticios con sabor local para los datos de prueba. */
final class DatosMexico
{
    private const NOMBRES = ['María', 'José', 'Guadalupe', 'Juan', 'Rosa', 'Luis', 'Ana', 'Jorge', 'Carmen', 'Miguel',
        'Verónica', 'Alejandro', 'Patricia', 'Francisco', 'Leticia', 'Ricardo', 'Elena', 'Fernando', 'Diana', 'Arturo',
        'Sofía', 'Héctor', 'Gabriela', 'Raúl', 'Adriana', 'Óscar', 'Mónica', 'Javier', 'Claudia', 'Roberto'];

    private const APELLIDOS = ['Hernández', 'García', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez',
        'Ramírez', 'Cruz', 'Flores', 'Gómez', 'Morales', 'Vázquez', 'Jiménez', 'Reyes', 'Díaz', 'Torres', 'Gutiérrez',
        'Ruiz', 'Mendoza', 'Aguilar', 'Ortiz', 'Castillo', 'Moreno', 'Romero', 'Álvarez', 'Chávez', 'Rivera', 'Juárez'];

    private const CALLES = ['Hidalgo', 'Juárez', 'Morelos', 'Allende', 'Zaragoza', 'Guerrero', 'Independencia', 'Reforma',
        '5 de Mayo', '16 de Septiembre', 'Matamoros', 'Aldama', 'Abasolo', 'Insurgentes', 'Emiliano Zapata', 'Las Flores'];

    private const COLONIAS = ['Centro', 'Col. Norte', 'Col. Sur', 'Las Lomas', 'El Mirador', 'San José', 'La Esperanza', 'Barrio Alto'];

    public static function nombreCompleto(): string
    {
        return self::uno(self::NOMBRES).' '.self::uno(self::APELLIDOS).' '.self::uno(self::APELLIDOS);
    }

    public static function direccion(): string
    {
        return 'Calle '.self::uno(self::CALLES).' #'.random_int(1, 450).', '.self::uno(self::COLONIAS);
    }

    /** @param list<string> $lista */
    private static function uno(array $lista): string
    {
        return $lista[array_rand($lista)];
    }
}
