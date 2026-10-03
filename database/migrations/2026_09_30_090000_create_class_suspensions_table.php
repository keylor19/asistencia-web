<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('suspension_date');
            $table->string('reason', 255);
            $table->timestamps();

            $table->unique(['group_id', 'suspension_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_suspensions');
    }
};
