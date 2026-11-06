<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fechas de cada semestre por año y docente; el calendario escolar cambia cada año.
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('number'); // 1 = I semestre, 2 = II semestre
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['user_id', 'year', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
