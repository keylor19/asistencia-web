<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $groups = Auth::user()->groups;
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();

        $groupId = $request->query('group'); // vacío = todavía no escoge grupo
        $subjectId = $request->query('subject'); // vacío = todas las subáreas

        $period = $request->query('period', 'day'); // day | week | month | semester1 | semester2 | all
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $records = collect();
        $group = null;

        if ($groupId) {
            $group = Group::findOrFail($groupId);
            $this->authorize('access', $group);

            $query = Attendance::with(['student', 'subject'])
                ->where('group_id', $groupId);

            // "Todo el registro" no filtra por fechas: toma todas las veces que se ha pasado lista.
            if ($period !== 'all') {
                $query->whereBetween('attendance_date', [$startDate, $endDate]);
            }

            if ($subjectId) {
                $query->where('subject_id', $subjectId);
            }

            $records = $query->orderBy('attendance_date')
                ->get()
                ->groupBy('student_id');

            if ($period === 'all' && $records->isNotEmpty()) {
                $all = $records->flatten();
                $startDate = $all->min('attendance_date');
                $endDate = $all->max('attendance_date');
            }
        }

        $minPercentage = $group->min_attendance_percentage ?? 80;
        $attendanceWeight = (float) ($group->attendance_weight ?? 5);

        $summary = $records->map(function ($items) use ($minPercentage, $attendanceWeight) {
            $percentage = Attendance::percentageOf($items);

            return [
                'student_id' => $items->first()->student_id,
                'name' => $items->first()->student->full_name,
                'presente' => $items->where('status', 'presente')->count(),
                'ausente' => $items->where('status', 'ausente')->count(),
                'tardia' => $items->where('status', 'tardia')->count(),
                'justificada' => $items->where('status', 'justificada')->count(),
                'suspendida' => $items->where('status', 'suspendida')->count(),
                'lecciones_perdidas' => (int) $items->whereIn('status', ['ausente', 'tardia'])->sum('lessons'),
                'porcentaje' => $percentage,
                'en_riesgo' => $percentage !== null && $percentage < $minPercentage,
                // Parte proporcional del valor de la asistencia: 90% de asistencia con valor 5% = 4.5%.
                'puntos' => $percentage === null ? null : round($percentage * $attendanceWeight / 100, 2),
            ];
        })->sortBy('name')->values();

        $groupPercentage = Attendance::percentageOf($records->flatten());
        $atRiskCount = $summary->where('en_riesgo', true)->count();

        $semesterYear = Carbon::parse($date)->year;
        $semesters = Semester::datesFor(Auth::id(), $semesterYear);

        return view('reports.index', compact(
            'groups', 'groupId', 'group', 'subjects', 'subjectId',
            'period', 'date', 'startDate', 'endDate', 'records', 'summary',
            'minPercentage', 'attendanceWeight', 'groupPercentage', 'atRiskCount',
            'semesterYear', 'semesters'
        ));
    }

    /**
     * Guarda el porcentaje mínimo de asistencia exigido al grupo y cuánto vale
     * la asistencia en la nota. Vuelve al reporte con los mismos filtros.
     */
    public function updateSettings(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $validated = $request->validate([
            'min_attendance_percentage' => 'required|integer|min:1|max:100',
            'attendance_weight' => 'required|numeric|min:0.01|max:100',
        ]);

        $group->update($validated);

        return redirect()
            ->route('reports.index', array_merge(
                $request->only(['subject', 'period', 'date']),
                ['group' => $group->id]
            ))
            ->with('success', 'Configuración de asistencia actualizada.');
    }

    /**
     * Reporte detallado de un estudiante: historial de asistencia en un periodo,
     * datos del encargado y bitácora de avisos de WhatsApp enviados.
     */
    public function student(Student $student, Request $request)
    {
        $data = $this->buildStudentReportData($student, $request);

        return view('reports.student', $data);
    }

    /**
     * Mismo reporte detallado del estudiante, exportado como PDF descargable.
     */
    public function studentPdf(Student $student, Request $request)
    {
        $data = $this->buildStudentReportData($student, $request);

        $pdf = Pdf::loadView('reports.student-pdf', $data)->setPaper('letter');

        $fileName = 'asistencia-' . str($student->full_name)->slug() . '-' . $data['date'] . '.pdf';

        return $pdf->download($fileName);
    }

    private function buildStudentReportData(Student $student, Request $request): array
    {
        $this->authorize('access', $student->group);

        $student->load('group');

        $period = $request->query('period', 'month'); // week | month | quarter | semester
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        [$startDate, $endDate] = $this->resolveStudentDateRange($period, $date);

        $attendances = $student->attendances()
            ->with('subject')
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderByDesc('attendance_date')
            ->get();

        $summary = [
            'presente' => $attendances->where('status', 'presente')->count(),
            'ausente' => $attendances->where('status', 'ausente')->count(),
            'tardia' => $attendances->where('status', 'tardia')->count(),
            'justificada' => $attendances->where('status', 'justificada')->count(),
            'suspendida' => $attendances->where('status', 'suspendida')->count(),
            'lecciones_perdidas' => (int) $attendances->whereIn('status', ['ausente', 'tardia'])->sum('lessons'),
        ];

        $notifications = $student->whatsappNotifications()
            ->with(['teacher', 'subject'])
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderByDesc('sent_at')
            ->get();

        $notifiedLookup = $notifications->keyBy(fn ($n) => $this->notificationKey(
            $n->attendance_date->format('Y-m-d'), $n->status, $n->subject_id
        ));

        $absencesAndLateness = $attendances->whereIn('status', ['ausente', 'tardia'])
            ->map(function ($item) use ($notifiedLookup) {
                $key = $this->notificationKey(
                    $item->attendance_date->format('Y-m-d'), $item->status, $item->subject_id
                );

                $item->notified_at = optional($notifiedLookup->get($key))->sent_at;

                return $item;
            });

        return compact(
            'student', 'attendances', 'summary', 'absencesAndLateness', 'notifications',
            'period', 'date', 'startDate', 'endDate'
        );
    }

    private function notificationKey(string $date, string $status, ?int $subjectId): string
    {
        return $date . '|' . $status . '|' . ($subjectId ?? 'null');
    }

    private function resolveDateRange(string $period, string $date): array
    {
        $carbon = Carbon::parse($date);

        return match ($period) {
            'week' => [$carbon->copy()->startOfWeek(), $carbon->copy()->endOfWeek()],
            'month' => [$carbon->copy()->startOfMonth(), $carbon->copy()->endOfMonth()],
            'semester1' => Semester::datesFor(Auth::id(), $carbon->year)[1],
            'semester2' => Semester::datesFor(Auth::id(), $carbon->year)[2],
            default => [$carbon->copy(), $carbon->copy()],
        };
    }

    /**
     * Guarda las fechas del I y II semestre de un año para el docente.
     * Vuelve al reporte con los mismos filtros.
     */
    public function updateSemesters(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'semester1_start' => 'required|date',
            'semester1_end' => 'required|date|after_or_equal:semester1_start',
            'semester2_start' => 'required|date|after:semester1_end',
            'semester2_end' => 'required|date|after_or_equal:semester2_start',
        ]);

        foreach ([1, 2] as $number) {
            Semester::updateOrCreate(
                ['user_id' => Auth::id(), 'year' => $validated['year'], 'number' => $number],
                [
                    'start_date' => $validated["semester{$number}_start"],
                    'end_date' => $validated["semester{$number}_end"],
                ]
            );
        }

        return redirect()
            ->route('reports.index', $request->only(['group', 'subject', 'period', 'date']))
            ->with('success', "Fechas de los semestres {$validated['year']} actualizadas.");
    }

    /**
     * Rango de fechas "hacia atrás" desde la fecha de referencia, usado en el
     * reporte por estudiante (última semana / mes / 3 meses / 6 meses).
     */
    private function resolveStudentDateRange(string $period, string $date): array
    {
        $end = Carbon::parse($date)->endOfDay();

        $start = match ($period) {
            'week' => $end->copy()->subDays(6)->startOfDay(),
            'quarter' => $end->copy()->subMonthsNoOverflow(3)->addDay()->startOfDay(),
            'semester' => $end->copy()->subMonthsNoOverflow(6)->addDay()->startOfDay(),
            default => $end->copy()->subMonthNoOverflow()->addDay()->startOfDay(),
        };

        return [$start, $end];
    }
}
