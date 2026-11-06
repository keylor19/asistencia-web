<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            // Cuánto vale la asistencia en la nota del periodo (ej. 5 = 5%); el estudiante obtiene la parte proporcional.
            $table->decimal('attendance_weight', 5, 2)->default(5)->after('min_attendance_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->dropColumn('attendance_weight');
        });
    }
};
