<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    protected $fillable = ['user_id', 'year', 'number', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Fechas de inicio y fin de cada semestre del año, como [1 => [inicio, fin], 2 => [inicio, fin]].
     * Usa las que configuró el docente; si no hay, I = enero–junio y II = julio–diciembre.
     */
    public static function datesFor(int $userId, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1);

        $dates = [
            1 => [$yearStart->copy(), $yearStart->copy()->month(6)->endOfMonth()],
            2 => [$yearStart->copy()->month(7), $yearStart->copy()->endOfYear()],
        ];

        static::where('user_id', $userId)
            ->where('year', $year)
            ->get()
            ->each(function ($semester) use (&$dates) {
                $dates[$semester->number] = [$semester->start_date, $semester->end_date];
            });

        return $dates;
    }

    /**
     * Número de semestre (1 o 2) en que cae una fecha, o null si cae fuera
     * de ambos (por ejemplo, en vacaciones de medio año).
     */
    public static function numberFor(int $userId, Carbon $date): ?int
    {
        foreach (static::datesFor($userId, $date->year) as $number => [$start, $end]) {
            if ($date->between($start->copy()->startOfDay(), $end->copy()->endOfDay())) {
                return $number;
            }
        }

        return null;
    }
}
