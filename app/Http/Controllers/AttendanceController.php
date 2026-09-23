<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use App\Models\WhatsappNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $subjectId = $request->query('subject', $this->defaultSubjectId($group, $date, $subjects));

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

    /**
     * Genera en PDF la lista de asistencia de un grupo para una fecha y subárea
     * específicas, con un cuadro de observaciones en blanco para imprimir y archivar.
     */
    public function dailyPdf(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $subjects = Subject::orderBy('name')->get();
        $subjectId = $request->query('subject', $this->defaultSubjectId($group, $date, $subjects));
        $subject = $subjectId ? $subjects->firstWhere('id', $subjectId) : null;

        $students = $group->students()
            ->where('active', 1)
            ->orderBy('full_name')
            ->get();

        $attendances = Attendance::where('group_id', $group->id)
            ->where('subject_id', $subjectId)
            ->whereDate('attendance_date', $date)
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function ($student) use ($attendances) {
            $attendance = $attendances->get($student->id);

            return (object) [
                'student' => $student,
                'status' => $attendance->status ?? null,
                'notes' => $attendance->notes ?? null,
            ];
        });

        $summary = [
            'presente' => $attendances->where('status', 'presente')->count(),
            'ausente' => $attendances->where('status', 'ausente')->count(),
            'tardia' => $attendances->where('status', 'tardia')->count(),
            'justificada' => $attendances->where('status', 'justificada')->count(),
            'sin_registrar' => $students->count() - $attendances->count(),
        ];

        $teacher = Auth::user();

        $pdf = Pdf::loadView('attendance.daily-pdf', compact(
            'group', 'rows', 'date', 'subject', 'summary', 'teacher'
        ))->setPaper('letter');

        $fileName = 'lista-asistencia-' . str($group->name)->slug() . '-' . $date . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Subárea que se selecciona por defecto al abrir la lista de un grupo: primero la que
     * ya se usó ese mismo día, luego la más reciente que se le haya pasado a ese grupo,
     * y solo si nunca se ha pasado asistencia se cae al orden alfabético.
     */
    private function defaultSubjectId(Group $group, string $date, \Illuminate\Support\Collection $subjects): ?int
    {
        return Attendance::where('group_id', $group->id)
            ->whereDate('attendance_date', $date)
            ->orderByDesc('created_at')
            ->value('subject_id')
            ?? Attendance::where('group_id', $group->id)
                ->orderByDesc('attendance_date')
                ->value('subject_id')
            ?? $subjects->first()->id ?? null;
    }
}
