<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Attendance;
use App\Models\ClassNote;
use App\Models\WhatsappNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();
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
        $existingLessons = $attendancesToday->pluck('lessons', 'student_id');

        $suspension = $group->suspensions()->whereDate('suspension_date', $date)->first();

        $classNote = $this->findClassNote($group, $subjectId, $date);

        return view('attendance.create', compact(
            'group', 'students', 'date', 'existing', 'existingNotes', 'existingLessons',
            'subjects', 'subjectId', 'suspension', 'classNote'
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
            'subject_id' => [
                'required',
                Rule::exists('subjects', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'attendance' => 'required|array',
            'attendance.*' => 'required|in:presente,ausente,tardia,justificada',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
            'lessons' => 'nullable|array',
            'lessons.*' => 'nullable|integer|min:1|max:' . $group->lessons_per_day,
            'class_note' => 'nullable|string|max:2000',
        ]);

        $noteContent = trim((string) ($validated['class_note'] ?? ''));

        if ($noteContent !== '') {
            $classNote = $this->findClassNote($group, $validated['subject_id'], $validated['date'])
                ?? new ClassNote([
                    'group_id' => $group->id,
                    'subject_id' => $validated['subject_id'],
                    'note_date' => $validated['date'],
                ]);

            $classNote->user_id = Auth::id();
            $classNote->content = $noteContent;
            $classNote->save();
        } else {
            $this->findClassNote($group, $validated['subject_id'], $validated['date'])?->delete();
        }

        foreach ($validated['attendance'] as $studentId => $status) {
            // No se usa updateOrCreate() porque su búsqueda compara la fecha como texto
            // plano, y no coincide con el formato que Eloquent guarda internamente para
            // columnas con cast de fecha (whereDate() sí compara solo la fecha, sin
            // importar el motor de base de datos).
            $attendance = Attendance::where('student_id', $studentId)
                ->where('subject_id', $validated['subject_id'])
                ->whereDate('attendance_date', $validated['date'])
                ->first() ?? new Attendance([
                    'student_id' => $studentId,
                    'attendance_date' => $validated['date'],
                    'subject_id' => $validated['subject_id'],
                ]);

            $attendance->group_id = $group->id;
            $attendance->user_id = Auth::id();
            $attendance->status = $status;
            $attendance->notes = $request->input("notes.$studentId");
            $attendance->lessons = $status === 'presente' ? null : $request->input("lessons.$studentId");
            $attendance->save();
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
            'subject_id' => [
                'nullable',
                Rule::exists('subjects', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'date' => 'required|date',
            'status' => 'required|in:ausente,tardia,suspendida',
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

        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();
        $subjectId = $request->query('subject', $this->defaultSubjectId($group, $date, $subjects));
        $subject = $subjectId ? $subjects->firstWhere('id', $subjectId) : null;

        $students = $group->students()
            ->where('active', 1)
            ->orderBy('full_name')
            ->get();

        $suspension = $group->suspensions()->whereDate('suspension_date', $date)->first();

        $attendances = $suspension
            ? Attendance::where('group_id', $group->id)
                ->whereNull('subject_id')
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id')
            : Attendance::where('group_id', $group->id)
                ->where('subject_id', $subjectId)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id');

        $rows = $students->map(function ($student) use ($attendances) {
            $attendance = $attendances->get($student->id);

            return (object) [
                'student' => $student,
                'status' => $attendance->status ?? null,
                'lessons' => $attendance->lessons ?? null,
                'notes' => $attendance->notes ?? null,
            ];
        });

        $summary = [
            'presente' => $attendances->where('status', 'presente')->count(),
            'ausente' => $attendances->where('status', 'ausente')->count(),
            'tardia' => $attendances->where('status', 'tardia')->count(),
            'justificada' => $attendances->where('status', 'justificada')->count(),
            'suspendida' => $attendances->where('status', 'suspendida')->count(),
            'sin_registrar' => $students->count() - $attendances->count(),
        ];

        $teacher = Auth::user();

        $classNote = $this->findClassNote($group, $subjectId, $date);

        $pdf = Pdf::loadView('attendance.daily-pdf', compact(
            'group', 'rows', 'date', 'subject', 'summary', 'teacher', 'suspension', 'classNote'
        ))->setPaper('letter');

        $fileName = 'lista-asistencia-' . str($group->name)->slug() . '-' . $date . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Subárea que se selecciona por defecto al abrir la lista de un grupo: primero la que
     * este mismo docente ya usó ese día, luego la más reciente que él le haya pasado a ese
     * grupo, y solo si nunca ha pasado asistencia se cae al orden alfabético de sus subáreas.
     * Se limita a las subáreas del docente autenticado para no mezclar datos de otros
     * docentes que compartan el mismo grupo.
     */
    private function defaultSubjectId(Group $group, string $date, \Illuminate\Support\Collection $subjects): ?int
    {
        $teacherId = Auth::id();

        return Attendance::where('group_id', $group->id)
            ->where('user_id', $teacherId)
            ->whereDate('attendance_date', $date)
            ->orderByDesc('created_at')
            ->value('subject_id')
            ?? Attendance::where('group_id', $group->id)
                ->where('user_id', $teacherId)
                ->orderByDesc('attendance_date')
                ->value('subject_id')
            ?? $subjects->first()->id ?? null;
    }

    /**
     * Busca la nota de "qué se trabajó ese día" para un grupo, subárea y fecha.
     * No usa updateOrCreate() por el mismo motivo que el resto del controlador:
     * su búsqueda compara la fecha como texto plano y no coincide con el formato
     * que Eloquent guarda internamente para columnas con cast de fecha.
     */
    private function findClassNote(Group $group, ?int $subjectId, string $date): ?ClassNote
    {
        return ClassNote::where('group_id', $group->id)
            ->where('subject_id', $subjectId)
            ->whereDate('note_date', $date)
            ->first();
    }
}
