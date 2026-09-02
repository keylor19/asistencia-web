<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Group;
use App\Models\Student;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $diurno = Group::where('shift', 'diurno')->first();
        $nocturno = Group::where('shift', 'nocturno')->first();

        $estudiantesDiurno = [
            'Nombre Apellido1 Apellido2',
            'Nombre Apellido1 Apellido2',
            // ... agrega aquí todos tus estudiantes reales del grupo diurno
        ];

        foreach ($estudiantesDiurno as $nombre) {
            Student::create([
                'group_id' => $diurno->id,
                'full_name' => $nombre,
            ]);
        }

        $estudiantesNocturno = [
            'Nombre Apellido1 Apellido2',
            // ... agrega aquí todos tus estudiantes reales del grupo nocturno
        ];

        foreach ($estudiantesNocturno as $nombre) {
            Student::create([
                'group_id' => $nocturno->id,
                'full_name' => $nombre,
            ]);
        }
    }
}