<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table): void {
            $table->id();
            // unique_external_id de SmartOLT: es la llave para cortar/reconectar.
            $table->string('external_id')->unique();
            $table->string('sn')->nullable()->index();
            $table->string('nombre')->nullable()->index();
            $table->string('direccion')->nullable();
            $table->string('contacto')->nullable();
            $table->string('olt_id')->nullable();
            $table->string('olt_nombre')->nullable();
            $table->string('zona')->nullable()->index();
            // Enabled / Disabled (administrative_status en SmartOLT)
            $table->string('estado_admin')->nullable()->index();
            // Online / Power fail / LOS / Offline
            $table->string('estado_red')->nullable();
            // Liga con tu base de datos externa de clientes (id, contrato, etc.)
            $table->string('cliente_externo_id')->nullable()->index();
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
