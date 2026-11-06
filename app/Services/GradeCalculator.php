<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\Group;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula la nota de cada estudiante en un rango de fechas (normalmente un semestre)
 * sumando lo que obtiene en cada componente: asistencia, pruebas, trabajo cotidiano,
 * proyectos y tareas, según el valor configurado en el grupo.
 */
class GradeCalculator
{
    /**
     * @return Collection fila por estudiante con 'student', 'components', 'total' y 'evaluated_weight'
     */
    public function forStudents(Group $group, Collection $students, ?int $subjectId, Carbon $start, Carbon $end): Collection
    {
        $evaluations = Evaluation::with('scores')
            ->where('group_id', $group->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->whereBetween('evaluation_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('evaluation_date')
            ->get()
            ->groupBy('category');

        $attendances = Attendance::where('group_id', $group->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->whereBetween('attendance_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->groupBy('student_id');

        $weights = [];
        foreach (Evaluation::CATEGORIES as $key => $category) {
            $weights += Evaluation::effectiveWeights(
                $evaluations->get($key, collect()),
                (float) $group->{$category['weight_column']}
            );
        }

        return $students->map(function ($student) use ($group, $evaluations, $attendances, $weights) {
            $attendanceWeight = (float) $group->attendance_weight;
            $attendancePercentage = Attendance::percentageOf($attendances->get($student->id, collect()));

            $components = [
                'asistencia' => [
                    'label' => 'Asistencia',
                    'weight' => $attendanceWeight,
                    'evaluated_weight' => $attendancePercentage === null ? 0 : $attendanceWeight,
                    'percentage' => $attendancePercentage,
                    'points' => $attendancePercentage === null ? null : round($attendancePercentage * $attendanceWeight / 100, 2),
                    'items' => [],
                ],
            ];

            foreach (Evaluation::CATEGORIES as $key => $category) {
                $items = $evaluations->get($key, collect())->map(function ($evaluation) use ($student, $weights) {
                    $points = optional($evaluation->scores->firstWhere('student_id', $student->id))->points;
                    // Sin nota cuenta como 0: no lo presentó.
                    $percentage = round(($points ?? 0) / $evaluation->max_points * 100, 2);
                    $weight = $weights[$evaluation->id];

                    return [
                        'evaluation' => $evaluation,
                        'points' => $points,
                        'percentage' => $percentage,
                        'weight' => $weight,
                        'grade' => round($percentage * $weight / 100, 2),
                    ];
                });

                $evaluatedWeight = round($items->sum('weight'), 2);
                $obtained = round($items->sum('grade'), 2);

                $components[$key] = [
                    'label' => $category['label'],
                    'weight' => (float) $group->{$category['weight_column']},
                    'evaluated_weight' => $evaluatedWeight,
                    // Rendimiento del estudiante en lo evaluado del componente (0–100).
                    'percentage' => $items->isEmpty() ? null
                        : ($evaluatedWeight > 0 ? round($obtained / $evaluatedWeight * 100, 2) : round($items->avg('percentage'), 2)),
                    'points' => $items->isEmpty() ? null : $obtained,
                    'items' => $items->all(),
                ];
            }

            $components = collect($components);

            return [
                'student' => $student,
                'components' => $components->all(),
                'total' => round($components->sum('points'), 2),
                'evaluated_weight' => round($components->sum('evaluated_weight'), 2),
            ];
        })->values();
    }
}
