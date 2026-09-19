<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['presente', 'ausente', 'tardia', 'justificada'])->default('presente');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'attendance_date', 'subject_id'], 'unique_student_day_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
