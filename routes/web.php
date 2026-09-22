<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubjectController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('groups.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/grupos', [GroupController::class, 'index'])->name('groups.index');

    Route::post('/asistencia/notificar', [AttendanceController::class, 'notify'])->name('attendance.notify');
    Route::get('/asistencia/{group}', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/asistencia/{group}', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/estudiantes', [StudentController::class, 'index'])->name('students.index');
    Route::get('/estudiantes/nuevo', [StudentController::class, 'create'])->name('students.create');
    Route::post('/estudiantes', [StudentController::class, 'store'])->name('students.store');
    Route::get('/estudiantes/{student}/editar', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/estudiantes/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/estudiantes/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/subareas', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/subareas/nueva', [SubjectController::class, 'create'])->name('subjects.create');
    Route::post('/subareas', [SubjectController::class, 'store'])->name('subjects.store');
    Route::get('/subareas/{subject}/editar', [SubjectController::class, 'edit'])->name('subjects.edit');
    Route::put('/subareas/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subareas/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reportes/estudiante/{student}', [ReportController::class, 'student'])->name('reports.student');
    Route::get('/reportes/estudiante/{student}/pdf', [ReportController::class, 'studentPdf'])->name('reports.student.pdf');
});

require __DIR__.'/auth.php';