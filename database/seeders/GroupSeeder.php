<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;
use App\Models\User;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $diurno = Group::create([
            'name' => 'Décimo - Desarrollo Web',
            'shift' => 'nocturno',
        ]);

        $nocturno = Group::create([
            'name' => 'Undécimo - Desarrollo Web',
            'shift' => 'diurno',
        ]);

        // Asigna ambos grupos al primer docente (ajusta según necesites)
        $teacher = User::first();
        $teacher->groups()->attach([$diurno->id, $nocturno->id]);
    }
}