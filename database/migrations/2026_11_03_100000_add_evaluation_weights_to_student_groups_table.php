<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            // Valor de cada componente en la nota del semestre; con asistencia (5) suman 100 por defecto.
            $table->decimal('tests_weight', 5, 2)->default(35)->after('attendance_weight');
            $table->decimal('daily_work_weight', 5, 2)->default(30)->after('tests_weight');
            $table->decimal('projects_weight', 5, 2)->default(20)->after('daily_work_weight');
            $table->decimal('homework_weight', 5, 2)->default(10)->after('projects_weight');
        });
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->dropColumn(['tests_weight', 'daily_work_weight', 'projects_weight', 'homework_weight']);
        });
    }
};
