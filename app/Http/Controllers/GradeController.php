<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\GradeCalculator;
use App\Services\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        [$startDate, $endDate] = Semester::datesFor(Auth::id(), $year)[$semester];

        $group = null;
        $rows = collect();

        if ($groupId) {
            $group = Group::findOrFail($groupId);
            $this->authorize('access', $group);

            $students = $group->students()->where('active', 1)->orderBy('full_name')->get();
            $rows = $calculator->forStudents($group, $students, $subjectId ? (int) $subjectId : null, $startDate, $endDate);
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
        [$startDate, $endDate] = Semester::datesFor(Auth::id(), $year)[$semester];

        $subjects = $this->subjectsToExport($request, $group, $startDate, $endDate);
        $students = $group->students()->where('active', 1)->orderBy('full_name')->get();

        $header = ['Id', 'Nombre', 'Trabajo cotidiano', 'Tareas', 'Prueba', 'Proyecto', 'Asistencia'];
        $columns = ['cotidiano', 'tareas', 'pruebas', 'proyectos', 'asistencia'];

        $writer = new XlsxWriter();

        foreach ($subjects as $subject) {
            $rows = [$header];

            foreach ($calculator->forStudents($group, $students, $subject?->id, $startDate, $endDate) as $row) {
                $identification = (string) $row['student']->identification;

                $rows[] = array_merge(
                    [ctype_digit($identification) && strlen($identification) <= 15 ? (int) $identification : $identification,
                     $row['student']->full_name],
                    array_map(fn ($key) => $row['components'][$key]['points'], $columns)
                );
            }

            $writer->addSheet($subject->name ?? 'Notas', $rows, [16, 42, 17, 10, 10, 10, 11]);
        }

        $filename = 'Notas ' . $group->name . ' ' . ($semester === 1 ? 'I' : 'II') . ' Semestre ' . $year . '.xlsx';

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
        [$startDate, $endDate] = Semester::datesFor(Auth::id(), $year)[$semester];

        // Se calcula cada subárea una sola vez para todos y luego se reparte por estudiante.
        $breakdowns = [];
        foreach ($this->subjectsToExport($request, $group, $startDate, $endDate) as $subject) {
            foreach ($calculator->forStudents($group, $students, $subject?->id, $startDate, $endDate) as $row) {
                $breakdowns[$row['student']->id][] = ['subject' => $subject, 'row' => $row];
            }
        }

        $pdf = Pdf::loadView('grades.student-pdf', compact(
            'group', 'students', 'breakdowns', 'year', 'semester', 'startDate', 'endDate'
        ))->setPaper('letter');

        return $pdf->download('Notas ' . $name . ' ' . ($semester === 1 ? 'I' : 'II') . ' Semestre ' . $year . '.pdf');
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

        [$startDate, $endDate] = Semester::datesFor(Auth::id(), $year)[$semester];

        $row = $calculator->forStudents(
            $student->group, collect([$student]), $subjectId ? (int) $subjectId : null, $startDate, $endDate
        )->first();

        return view('grades.student', compact(
            'student', 'subjects', 'subjectId', 'year', 'semester', 'startDate', 'endDate', 'row'
        ));
    }

    /**
     * Año y semestre pedidos; por defecto el semestre en que cae hoy.
     */
    private function resolveSemester(Request $request): array
    {
        $today = Carbon::today();

        $year = (int) $request->query('year', $today->year);
        $semester = (int) $request->query('semester', Semester::numberFor(Auth::id(), $today) ?? ($today->month <= 6 ? 1 : 2));

        return [$year, in_array($semester, [1, 2], true) ? $semester : 1];
    }
}
