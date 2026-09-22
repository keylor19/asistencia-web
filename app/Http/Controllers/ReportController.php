<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $groups = Auth::user()->groups;
        $subjects = Subject::orderBy('name')->get();

        $groupId = $request->query('group', $groups->first()->id ?? null);
        $subjectId = $request->query('subject'); // vacío = todas las subáreas

        $period = $request->query('period', 'day'); // day | week | month
        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        [$startDate, $endDate] = $this->resolveDateRange($period, $date);

        $records = collect();
        $group = null;

        if ($groupId) {
            $group = Group::findOrFail($groupId);
            $this->authorize('access', $group);

            $query = Attendance::with(['student', 'subject'])
                ->where('group_id', $groupId)
                ->whereBetween('attendance_date', [$startDate, $endDate]);

            if ($subjectId) {
                $query->where('subject_id', $subjectId);
            }

            $records = $query->orderBy('attendance_date')
                ->get()
                ->groupBy('student_id');
        }

        $summary = $records->map(function ($items) {
            return [
                'student_id' => $items->first()->student_id,
                'name' => $items->first()->student->full_name,
                'presente' => $items->where('status', 'presente')->count(),
                'ausente' => $items->where('status', 'ausente')->count(),
                'tardia' => $items->where('status', 'tardia')->count(),
                'justificada' => $items->where('status', 'justificada')->count(),
            ];
        })->sortBy('name')->values();

        return view('reports.index', compact(
            'groups', 'groupId', 'group', 'subjects', 'subjectId',
            'period', 'date', 'startDate', 'endDate', 'records', 'summary'
        ));
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
            default => [$carbon->copy(), $carbon->copy()],
        };
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
