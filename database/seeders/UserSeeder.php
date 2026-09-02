<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Keilor Docente',
            'email' => 'keilor.duran.acuna@mep.go.cr',
            'password' => Hash::make('123456789'),
        ]);

        User::create([
            'name' => 'Docente Prueba',
            'email' => 'docente2@colegio.cr',
            'password' => Hash::make('123456789'),
        ]);
    }
}