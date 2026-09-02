<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Subject;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Muestra la lista de estudiantes de un grupo para pasar lista en una fecha y subárea.
     */
    public function create(Request $request, Group $group)
    {
        $this->authorizeGroup($group->id);

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $subjects = Subject::orderBy('name')->get();
        $subjectId = $request->query('subject', $subjects->first()->id ?? null);

        $students = $group->students()
            ->where('active', 1)
            ->orderBy('full_name')
            ->get();

        $attendancesToday = Attendance::where('group_id', $group->id)
            ->where('subject_id', $subjectId)
            ->whereDate('attendance_date', $date)
            ->get();

        $existing = $attendancesToday->pluck('status', 'student_id');
        $existingNotes = $attendancesToday->pluck('notes', 'student_id');

        return view('attendance.create', compact(
            'group', 'students', 'date', 'existing', 'existingNotes', 'subjects', 'subjectId'
        ));
    }

    /**
     * Guarda la asistencia marcada para todos los estudiantes del grupo en una subárea y fecha.
     */
    public function store(Request $request, Group $group)
    {
        $this->authorizeGroup($group->id);

        $validated = $request->validate([
            'date' => 'required|date',
            'subject_id' => 'required|exists:subjects,id',
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:presente,ausente,tardia,justificada',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        foreach ($validated['attendance'] as $studentId => $status) {
            Attendance::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'attendance_date' => $validated['date'],
                    'subject_id' => $validated['subject_id'],
                ],
                [
                    'group_id' => $group->id,
                    'user_id' => Auth::id(),
                    'status' => $status,
                    'notes' => $request->input("notes.$studentId"),
                ]
            );
        }

        return redirect()
            ->route('attendance.create', [
                'group' => $group->id,
                'date' => $validated['date'],
                'subject' => $validated['subject_id'],
            ])
            ->with('success', 'Asistencia guardada correctamente.');
    }

    private function authorizeGroup(int $groupId): void
    {
        $perteneceAlGrupo = Auth::user()->groups()->where('student_groups.id', $groupId)->exists();

        abort_unless($perteneceAlGrupo, 403, 'No tienes acceso a este grupo.');
    }
}