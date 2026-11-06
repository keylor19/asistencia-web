<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Valor de esta evaluación en la nota (ej. Prueba 1 = 15%). Null = se reparte en partes
            // iguales lo que sobre del valor del componente.
            $table->decimal('weight', 5, 2)->nullable()->after('max_points');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
