<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['ausente', 'tardia']);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 20);
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
