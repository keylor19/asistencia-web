<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // varchar en vez de enum para poder agregar componentes nuevos sin alterar la columna.
            $table->string('category', 20); // pruebas | cotidiano | proyectos | tareas
            $table->string('name', 150);
            $table->text('description')->nullable(); // qué realizaron los estudiantes
            $table->date('evaluation_date');
            $table->decimal('max_points', 6, 2);
            $table->timestamps();

            $table->index(['group_id', 'category', 'evaluation_date']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('points', 6, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluations');
    }
};
