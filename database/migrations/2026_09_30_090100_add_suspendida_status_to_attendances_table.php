<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se cambia de enum a varchar para no tener que alterar el tipo de columna
        // cada vez que se agregue un nuevo estado de asistencia (ej. "suspendida").
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status', 20)->default('presente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['presente', 'ausente', 'tardia', 'justificada'])
                ->default('presente')
                ->change();
        });
    }
};
