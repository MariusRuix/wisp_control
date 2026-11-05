<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Los operadores no se borran (la bitácora y los pagos guardan quién fue): se desactivan.
            $table->boolean('activo')->default(true)->after('password');
            $table->timestamp('ultimo_acceso')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['activo', 'ultimo_acceso']);
        });
    }
};
