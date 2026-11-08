<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\ClassSuspensionController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\GradeController;
use App\Models\Evaluation;

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
    Route::get('/grupos/{group}/editar', [GroupController::class, 'edit'])->name('groups.edit');
    Route::put('/grupos/{group}', [GroupController::class, 'update'])->name('groups.update');

    Route::post('/asistencia/notificar', [AttendanceController::class, 'notify'])->name('attendance.notify');
    Route::get('/asistencia/{group}/pdf', [AttendanceController::class, 'dailyPdf'])->name('attendance.daily-pdf');
    Route::get('/asistencia/{group}/suspender', [ClassSuspensionController::class, 'create'])->name('suspensions.create');
    Route::post('/asistencia/{group}/suspender', [ClassSuspensionController::class, 'store'])->name('suspensions.store');
    Route::get('/asistencia/{group}/suspender/avisar', [ClassSuspensionController::class, 'notify'])->name('suspensions.notify');
    Route::delete('/asistencia/{group}/suspender', [ClassSuspensionController::class, 'destroy'])->name('suspensions.destroy');
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
    Route::put('/reportes/semestres', [ReportController::class, 'updateSemesters'])->name('reports.semesters');
    Route::put('/reportes/{group}/configuracion',[ReportController::class, 'updateSettings'])->name('reports.settings');
    Route::get('/reportes/estudiante/{student}', [ReportController::class, 'student'])->name('reports.student');
    Route::get('/reportes/estudiante/{student}/pdf', [ReportController::class, 'studentPdf'])->name('reports.student.pdf');

    // Componentes de la nota: /evaluaciones/pruebas, /evaluaciones/cotidiano, /evaluaciones/proyectos, /evaluaciones/tareas
    Route::whereIn('category', array_keys(Evaluation::CATEGORIES))->group(function () {
        Route::get('/evaluaciones/{category}', [EvaluationController::class, 'index'])->name('evaluations.index');
        Route::post('/evaluaciones/{category}', [EvaluationController::class, 'store'])->name('evaluations.store');
        Route::put('/evaluaciones/{category}/valor/{group}', [EvaluationController::class, 'updateWeight'])->name('evaluations.weight');
    });
    Route::get('/evaluacion/{evaluation}', [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::put('/evaluacion/{evaluation}', [EvaluationController::class, 'update'])->name('evaluations.update');
    Route::delete('/evaluacion/{evaluation}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy');

    Route::get('/notas', [GradeController::class, 'index'])->name('grades.index');
    Route::put('/notas/{group}/notas-a-mano', [GradeController::class, 'storeManualGrades'])->name('grades.manual');
    Route::get('/notas/{group}/excel', [GradeController::class, 'export'])->name('grades.export');
    Route::get('/notas/{group}/pdf', [GradeController::class, 'groupPdf'])->name('grades.group-pdf');
    Route::get('/notas/estudiante/{student}/pdf', [GradeController::class, 'studentPdf'])->name('grades.student-pdf');
    Route::get('/notas/estudiante/{student}', [GradeController::class, 'student'])->name('grades.student');
});

require __DIR__.'/auth.php';