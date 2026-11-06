<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $table = 'student_groups'; // la tabla real se llama así, no "groups"

    protected $fillable = [
        'name', 'shift', 'type', 'lessons_per_day', 'min_attendance_percentage', 'attendance_weight',
        'tests_weight', 'daily_work_weight', 'projects_weight', 'homework_weight',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_group');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function suspensions(): HasMany
    {
        return $this->hasMany(ClassSuspension::class);
    }

    public function classNotes(): HasMany
    {
        return $this->hasMany(ClassNote::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Suma de los valores de todos los componentes de la nota (asistencia incluida).
     * Debería dar 100; la interfaz avisa cuando no.
     */
    public function totalWeight(): float
    {
        $columns = array_column(Evaluation::CATEGORIES, 'weight_column');
        $columns[] = 'attendance_weight';

        return round(array_sum(array_map(fn ($column) => (float) $this->{$column}, $columns)), 2);
    }
}