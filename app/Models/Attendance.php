<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'student_id', 'group_id', 'subject_id', 'user_id', 'attendance_date', 'status', 'lessons', 'notes'
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    /**
     * Porcentaje de asistencia sobre las veces que se pasó lista. Las clases
     * suspendidas no cuentan; solo ausencias y tardías rebajan, la justificada no.
     * Devuelve null si no hay registros que contar.
     */
    public static function percentageOf($items): ?float
    {
        $counted = $items->where('status', '!=', 'suspendida');

        if ($counted->isEmpty()) {
            return null;
        }

        $attended = $counted->whereIn('status', ['presente', 'justificada'])->count();

        return round($attended / $counted->count() * 100, 1);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}