<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\ManualGrade;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\GradeCalculator;
use App\Services\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

/**
 * Nota del semestre por estudiante: suma de lo obtenido en asistencia, pruebas,
 * trabajo cotidiano, proyectos y tareas.
 */
class GradeController extends Controller
{
    public function index(Request $request, GradeCalculator $calculator)
    {
        $groups = Auth::user()->groups;
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();

        $groupId = $request->query('group'); // vacío = todavía no escoge grupo
        $subjectId = $request->query('subject');
        [$year, $semester] = $this->resolveSemester($request);

        [$startDate, $endDate] = $this->periodDates($year, $semester);

        $group = null;
        $rows = collect();

        if ($groupId) {
            $group = Group::findOrFail($groupId);
            $this->authorize('access', $group);

            $students = $group->students()->where('active', 1)->orderBy('full_name')->get();
            $subject = $subjectId ? (int) $subjectId : null;

            $rows = $semester === 'anual'
                ? $calculator->annual($group, $students, $subject, Semester::datesFor(Auth::id(), $year))
                : $calculator->forStudents($group, $students, $subject, $startDate, $endDate);
        }

        return view('grades.index', compact(
            'groups', 'group', 'groupId', 'subjects', 'subjectId',
            'year', 'semester', 'startDate', 'endDate', 'rows'
        ));
    }

    /**
     * Excel de notas del grupo con una hoja por subárea: Id, Nombre y lo que
     * obtuvo cada estudiante en cada componente.
     */
    public function export(Request $request, Group $group, GradeCalculator $calculator)
    {
        $this->authorize('access', $group);

        [$year, $semester] = $this->resolveSemester($request);
        [$startDate, $endDate] = $this->periodDates($year, $semester);

        $subjects = $this->subjectsToExport($request, $group, $startDate, $endDate);
        $students = $group->students()->where('active', 1)->orderBy('full_name')->get();

        if ($semester === 'anual') {
            $header = ['Id', 'Nombre', 'I Semestre', 'II Semestre', 'Promedio anual', 'Condición'];
            $widths = [16, 42, 12, 12, 15, 12];
            $semesterDates = Semester::datesFor(Auth::id(), $year);
            $rowsFor = fn ($subject) => $calculator->annual($group, $students, $subject?->id, $semesterDates)
                ->map(fn ($row) => [
                    $row['first']['total'], $row['second']['total'], $row['average'], $row['status'] ?? 'Pendiente',
                ]);
        } else {
            $header = ['Id', 'Nombre', 'Trabajo cotidiano', 'Tareas', 'Prueba', 'Proyecto', 'Asistencia'];
            $widths = [16, 42, 17, 10, 10, 10, 11];
            $columns = ['cotidiano', 'tareas', 'pruebas', 'proyectos', 'asistencia'];
            $rowsFor = fn ($subject) => $calculator->forStudents($group, $students, $subject?->id, $startDate, $endDate)
                ->map(fn ($row) => array_map(fn ($key) => $row['components'][$key]['points'], $columns));
        }

        $writer = new XlsxWriter();

        foreach ($subjects as $subject) {
            $rows = [$header];

            // Las filas vienen en el mismo orden que $students.
            foreach ($rowsFor($subject)->values() as $index => $values) {
                $student = $students[$index];
                $identification = (string) $student->identification;

                $rows[] = array_merge(
                    [ctype_digit($identification) && strlen($identification) <= 15 ? (int) $identification : $identification,
                     $student->full_name],
                    $values
                );
            }

            $writer->addSheet($subject->name ?? 'Notas', $rows, $widths);
        }

        $filename = 'Notas ' . $group->name . ' ' . $this->periodLabel($year, $semester) . '.xlsx';

        return response()->download($writer->save(), $filename)->deleteFileAfterSend();
    }

    /**
     * PDF con el desglose de notas por subárea de un estudiante, para entregárselo.
     */
    public function studentPdf(Request $request, Student $student, GradeCalculator $calculator)
    {
        $this->authorize('access', $student->group);

        return $this->gradesPdf($request, $student->group, collect([$student]), $calculator, $student->full_name);
    }

    /**
     * PDF de todo el grupo: una hoja por estudiante con su desglose por subárea.
     */
    public function groupPdf(Request $request, Group $group, GradeCalculator $calculator)
    {
        $this->authorize('access', $group);

        $students = $group->students()->where('active', 1)->orderBy('full_name')->get();

        return $this->gradesPdf($request, $group, $students, $calculator, $group->name);
    }

    private function gradesPdf(Request $request, Group $group, $students, GradeCalculator $calculator, string $name)
    {
        [$year, $semester] = $this->resolveSemester($request);
        [$startDate, $endDate] = $this->periodDates($year, $semester);
        $semesterDates = Semester::datesFor(Auth::id(), $year);

        // Se calcula cada subárea una sola vez para todos y luego se reparte por estudiante.
        $breakdowns = [];
        foreach ($this->subjectsToExport($request, $group, $startDate, $endDate) as $subject) {
            $rows = $semester === 'anual'
                ? $calculator->annual($group, $students, $subject?->id, $semesterDates)
                : $calculator->forStudents($group, $students, $subject?->id, $startDate, $endDate);

            foreach ($rows as $row) {
                $breakdowns[$row['student']->id][] = ['subject' => $subject, 'row' => $row];
            }
        }

        $view = $semester === 'anual' ? 'grades.annual-pdf' : 'grades.student-pdf';
        $pdf = Pdf::loadView($view, compact(
            'group', 'students', 'breakdowns', 'year', 'semester', 'startDate', 'endDate'
        ))->setPaper('letter');

        return $pdf->download('Notas ' . $name . ' ' . $this->periodLabel($year, $semester) . '.pdf');
    }

    /**
     * Guarda a mano la nota de un semestre, solo para estudiantes que no tienen ninguna
     * nota en el sistema en ese semestre (ej. ingresó de otro colegio o el semestre no
     * se llevó en el sistema). Vacío = se borra.
     */
    public function storeManualGrades(Request $request, Group $group, GradeCalculator $calculator)
    {
        $this->authorize('access', $group);

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'subject' => ['nullable', Rule::exists('subjects', 'id')->where('user_id', Auth::id())],
            'grades' => 'nullable|array',
            'grades.*' => 'array',
            'grades.*.*' => 'nullable|numeric|min:0|max:100',
        ], [
            'grades.*.*.max' => 'La nota no puede ser mayor que 100.',
            'grades.*.*.min' => 'La nota no puede ser negativa.',
        ]);

        $subjectId = $validated['subject'] ?? null;
        $students = $group->students()->get();

        // Quién puede llevar nota a mano en cada semestre: solo si no tiene notas en el sistema.
        $annual = $calculator->annual($group, $students, $subjectId ? (int) $subjectId : null, Semester::datesFor(Auth::id(), $validated['year']))
            ->keyBy(fn ($row) => $row['student']->id);

        $skipped = 0;

        DB::transaction(function () use ($validated, $annual, $subjectId, &$skipped) {
            foreach ($validated['grades'] ?? [] as $semester => $grades) {
                if (! in_array((int) $semester, [1, 2], true)) {
                    continue;
                }

                foreach ($grades as $studentId => $grade) {
                    $row = $annual->get((int) $studentId);

                    // Solo estudiantes del grupo y sin notas en el sistema ese semestre.
                    if (! $row || ! $row[(int) $semester === 1 ? 'first' : 'second']['can_type']) {
                        $skipped++;

                        continue;
                    }

                    $key = [
                        'student_id' => $studentId, 'subject_id' => $subjectId,
                        'year' => $validated['year'], 'semester' => (int) $semester,
                    ];

                    if ($grade === null || $grade === '') {
                        ManualGrade::where($key)->delete();
                    } else {
                        ManualGrade::updateOrCreate($key, ['grade' => $grade, 'user_id' => Auth::id()]);
                    }
                }
            }
        });

        return redirect()
            ->route('grades.index', [
                'group' => $group->id, 'subject' => $subjectId, 'semester' => 'anual', 'year' => $validated['year'],
            ])
            ->with('success', $skipped
                ? "Notas guardadas. Se ignoraron {$skipped} porque esos estudiantes ya tienen notas en el sistema."
                : 'Notas guardadas.');
    }

    /**
     * La subárea filtrada, o todas las que tienen evaluaciones o listas en el grupo
     * durante el semestre. Sin subáreas se exporta una sola hoja con todo (null).
     */
    private function subjectsToExport(Request $request, Group $group, Carbon $start, Carbon $end)
    {
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name');

        if ($request->query('subject')) {
            return collect([$subjects->findOrFail($request->query('subject'))]);
        }

        $range = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
        $usedIds = Evaluation::where('group_id', $group->id)->whereBetween('evaluation_date', $range)->pluck('subject_id')
            ->merge(Attendance::where('group_id', $group->id)->whereBetween('attendance_date', $range)->pluck('subject_id'))
            ->merge(ManualGrade::whereIn('student_id', $group->students()->select('id'))
                ->whereIn('year', [$start->year, $end->year])->pluck('subject_id'))
            ->filter()
            ->unique();

        $used = $subjects->whereIn('id', $usedIds)->get();

        return $used->isEmpty() ? collect([null]) : $used;
    }

    /**
     * Detalle de la nota de un estudiante: cada evaluación con sus puntos y lo
     * que aporta cada componente.
     */
    public function student(Request $request, Student $student, GradeCalculator $calculator)
    {
        $this->authorize('access', $student->group);

        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();
        $subjectId = $request->query('subject');
        [$year, $semester] = $this->resolveSemester($request);

        // El detalle es por semestre; desde el promedio anual se abre el I.
        if ($semester === 'anual') {
            $semester = 1;
        }

        [$startDate, $endDate] = $this->periodDates($year, $semester);

        $row = $calculator->forStudents(
            $student->group, collect([$student]), $subjectId ? (int) $subjectId : null, $startDate, $endDate
        )->first();

        return view('grades.student', compact(
            'student', 'subjects', 'subjectId', 'year', 'semester', 'startDate', 'endDate', 'row'
        ));
    }

    /**
     * Año y semestre pedidos (1, 2 o 'anual'); por defecto el semestre en que cae hoy.
     */
    private function resolveSemester(Request $request): array
    {
        $today = Carbon::today();

        $year = (int) $request->query('year', $today->year);

        if ($request->query('semester') === 'anual') {
            return [$year, 'anual'];
        }

        $semester = (int) $request->query('semester', Semester::numberFor(Auth::id(), $today) ?? ($today->month <= 6 ? 1 : 2));

        return [$year, in_array($semester, [1, 2], true) ? $semester : 1];
    }

    /**
     * Fechas del semestre, o del inicio del I al final del II para el promedio anual.
     */
    private function periodDates(int $year, int|string $semester): array
    {
        $dates = Semester::datesFor(Auth::id(), $year);

        return $semester === 'anual' ? [$dates[1][0], $dates[2][1]] : $dates[$semester];
    }

    private function periodLabel(int $year, int|string $semester): string
    {
        return match ($semester) {
            'anual' => 'Promedio anual ' . $year,
            1 => 'I Semestre ' . $year,
            default => 'II Semestre ' . $year,
        };
    }
}
