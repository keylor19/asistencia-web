<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_group');
    }
};
