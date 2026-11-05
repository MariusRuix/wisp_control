<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acciones_servicio', function (Blueprint $table): void {
            // true = corte/reconexión generada por cobranza (no por un operador)
            $table->boolean('automatica')->default(false)->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('acciones_servicio', function (Blueprint $table): void {
            $table->dropColumn('automatica');
        });
    }
};
