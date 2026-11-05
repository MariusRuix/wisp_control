<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // admin | operador (Cajero/Técnico). Los nuevos nacen con el rol más limitado.
            $table->string('rol', 20)->default('operador')->after('password');
        });

        // Quienes ya existían eran "todo poderosos": se quedan como administradores
        // para que nadie pierda acceso. Después se puede bajar a quien corresponda.
        DB::table('users')->update(['rol' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('rol');
        });
    }
};
