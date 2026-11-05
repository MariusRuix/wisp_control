<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Onu;
use Illuminate\Database\Seeder;

/** ONUs ficticias (como si vinieran de SmartOLT). IDs "DEMO-…". */
final class OnusDemoSeeder extends Seeder
{
    public const TOTAL = 45;

    public function run(): void
    {
        $faker = fake();
        $zonas = ['Centro', 'Norte', 'Sur', 'Las Lomas', 'El Mirador'];
        $olts = ['OLT-Centro', 'OLT-Norte'];

        for ($i = 1; $i <= self::TOTAL; $i++) {
            $id = sprintf('DEMO-%04d', $i);

            Onu::updateOrCreate(['external_id' => $id], [
                'sn' => ($i % 3 === 0 ? 'ZTEG' : 'HWTC').strtoupper(bin2hex(random_bytes(4))),
                'nombre' => 'onu-'.$i,
                'direccion' => DatosMexico::direccion(),
                'contacto' => $faker->numerify('55########'),
                'olt_id' => (string) (($i % 2) + 1),
                'olt_nombre' => $olts[$i % 2],
                'zona' => $zonas[$i % count($zonas)],
                'estado_admin' => 'Enabled',
                // Mayoría en línea, algunas con problemas para que se vean en Fallas
                'estado_red' => $faker->randomElement(array_merge(array_fill(0, 8, 'Online'), ['LOS', 'Power fail', 'Offline'])),
                'sincronizado_en' => now(),
            ]);
        }
    }
}
