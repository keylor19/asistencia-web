<?php

namespace App\Http\Controllers;

use App\Models\Group;
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
            $this->authorizeGroup($groupId);

            $query = Attendance::with(['student', 'subject'])
                ->where('group_id', $groupId)
                ->whereBetween('attendance_date', [$startDate, $endDate]);

            if ($subjectId) {
                $query->where('subject_id', $subjectId);
            }

            $records = $query->orderBy('attendance_date')
                ->get()
                ->groupBy(fn ($record) => $record->student->full_name);
        }

        $summary = $records->map(function ($items, $studentName) {
            return [
                'name' => $studentName,
                'presente' => $items->where('status', 'presente')->count(),
                'ausente' => $items->where('status', 'ausente')->count(),
                'tardia' => $items->where('status', 'tardia')->count(),
                'justificada' => $items->where('status', 'justificada')->count(),
            ];
        })->values();

        return view('reports.index', compact(
            'groups', 'groupId', 'group', 'subjects', 'subjectId',
            'period', 'date', 'startDate', 'endDate', 'records', 'summary'
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

    private function authorizeGroup(int $groupId): void
    {
        $perteneceAlGrupo = Auth::user()->groups()->where('student_groups.id', $groupId)->exists();

        abort_unless($perteneceAlGrupo, 403, 'No tienes acceso a este grupo.');
    }
}