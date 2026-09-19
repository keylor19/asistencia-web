<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Reporte detallado de un estudiante: historial completo de asistencia,
     * datos del encargado y bitácora de avisos de WhatsApp enviados.
     */
    public function student(Student $student)
    {
        $this->authorize('access', $student->group);

        $student->load('group');

        $attendances = $student->attendances()
            ->with('subject')
            ->orderByDesc('attendance_date')
            ->get();

        $summary = [
            'presente' => $attendances->where('status', 'presente')->count(),
            'ausente' => $attendances->where('status', 'ausente')->count(),
            'tardia' => $attendances->where('status', 'tardia')->count(),
            'justificada' => $attendances->where('status', 'justificada')->count(),
        ];

        $absencesAndLateness = $attendances->whereIn('status', ['ausente', 'tardia']);

        $notifications = $student->whatsappNotifications()
            ->with(['teacher', 'subject'])
            ->orderByDesc('sent_at')
            ->get();

        return view('reports.student', compact(
            'student', 'attendances', 'summary', 'absencesAndLateness', 'notifications'
        ));
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
}
