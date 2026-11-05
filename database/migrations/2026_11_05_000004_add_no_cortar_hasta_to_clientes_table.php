<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            // Promesa de pago / convenio: no cortar automáticamente hasta esta fecha (inclusive)
            $table->date('no_cortar_hasta')->nullable()->after('onu_id');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('no_cortar_hasta');
        });
    }
};
