<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota de un semestre escrita a mano (por ejemplo el I semestre de 2026, antes de
        // usar el sistema). Si existe, reemplaza la nota calculada en el promedio anual.
        Schema::create('manual_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('semester'); // 1 = I semestre, 2 = II semestre
            $table->decimal('grade', 5, 2);
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_grades');
    }
};
