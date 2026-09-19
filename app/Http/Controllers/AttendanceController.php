<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use App\Models\WhatsappNotification;
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
        $this->authorize('access', $group);

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
        $this->authorize('access', $group);

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

    /**
     * Registra que el docente hizo clic en "Avisar por WhatsApp" para un estudiante.
     * No confirma que el mensaje se haya enviado dentro de WhatsApp, solo que se abrió el chat.
     */
    public function notify(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'group_id' => 'required|exists:student_groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'date' => 'required|date',
            'status' => 'required|in:ausente,tardia',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $this->authorize('access', $group);

        $student = Student::findOrFail($validated['student_id']);
        abort_unless($student->group_id === $group->id, 404);
        abort_unless($student->whatsapp_phone, 422, 'El estudiante no tiene teléfono de encargado registrado.');

        WhatsappNotification::create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'subject_id' => $validated['subject_id'] ?? null,
            'user_id' => Auth::id(),
            'attendance_date' => $validated['date'],
            'status' => $validated['status'],
            'guardian_name' => $student->guardian_name,
            'guardian_phone' => $student->guardian_phone,
            'sent_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
