<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            // Porcentaje mínimo de asistencia exigido; debajo de este valor el estudiante sale "en riesgo" en reportes.
            $table->unsignedTinyInteger('min_attendance_percentage')->default(80)->after('lessons_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->dropColumn('min_attendance_percentage');
        });
    }
};
