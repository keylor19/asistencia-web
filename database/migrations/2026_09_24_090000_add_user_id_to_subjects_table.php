<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('name')->constrained('users')->cascadeOnDelete();
        });

        // Cada subárea existente se asigna al docente que más la ha usado en asistencias
        // (o al primer usuario del sistema si nunca se usó), para no dejar subáreas huérfanas.
        $fallbackUserId = DB::table('users')->orderBy('id')->value('id');

        foreach (DB::table('subjects')->get() as $subject) {
            $ownerId = DB::table('attendances')
                ->where('subject_id', $subject->id)
                ->select('user_id', DB::raw('count(*) as total'))
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->value('user_id');

            DB::table('subjects')
                ->where('id', $subject->id)
                ->update(['user_id' => $ownerId ?? $fallbackUserId]);
        }
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
