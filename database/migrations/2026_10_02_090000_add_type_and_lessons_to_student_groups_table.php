<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            // varchar en vez de enum para poder agregar tipos nuevos sin alterar la columna.
            $table->string('type', 20)->default('tecnico')->after('shift');
            $table->unsignedTinyInteger('lessons_per_day')->default(8)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('student_groups', function (Blueprint $table) {
            $table->dropColumn(['type', 'lessons_per_day']);
        });
    }
};
