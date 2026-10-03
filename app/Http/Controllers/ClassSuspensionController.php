<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassSuspension;
use App\Models\Group;
use App\Models\WhatsappNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassSuspensionController extends Controller
{
    /**
     * Formulario para suspender las clases de un grupo en una fecha, con el motivo.
     */
    public function create(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $existing = $group->suspensions()->whereDate('suspension_date', $date)->first();

        return view('attendance.suspend-create', compact('group', 'date', 'existing'));
    }

    /**
     * Registra la suspensión y marca a todos los estudiantes activos del grupo
     * como "suspendida" ese día (sin importar la subárea), para que quede reflejado
     * en los reportes que ese día no hubo clases.
     */
    public function store(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $validated = $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:255',
        ]);

        // No se usa updateOrCreate() porque su búsqueda compara la fecha como texto plano
        // y no coincide con el formato que Eloquent guarda internamente para columnas con
        // cast de fecha (whereDate() sí compara solo la fecha, sin importar el motor de BD).
        $suspension = $group->suspensions()->whereDate('suspension_date', $validated['date'])->first()
            ?? new ClassSuspension([
                'group_id' => $group->id,
                'suspension_date' => $validated['date'],
            ]);

        $suspension->user_id = Auth::id();
        $suspension->reason = $validated['reason'];
        $suspension->save();

        $students = $group->students()->where('active', 1)->get();

        foreach ($students as $student) {
            $attendance = Attendance::where('student_id', $student->id)
                ->whereNull('subject_id')
                ->whereDate('attendance_date', $validated['date'])
                ->first() ?? new Attendance([
                    'student_id' => $student->id,
                    'attendance_date' => $validated['date'],
                    'subject_id' => null,
                ]);

            $attendance->group_id = $group->id;
            $attendance->user_id = Auth::id();
            $attendance->status = 'suspendida';
            $attendance->notes = 'Suspensión de clases: ' . $validated['reason'];
            $attendance->save();
        }

        return redirect()
            ->route('suspensions.notify', ['group' => $group->id, 'date' => $validated['date']])
            ->with('success', 'Suspensión registrada. Ahora podés avisarle a los encargados por WhatsApp.');
    }

    /**
     * Lista a los encargados de todos los estudiantes activos del grupo para avisarles
     * por WhatsApp el motivo de la suspensión, uno por uno (igual que con tardías/ausencias).
     */
    public function notify(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));

        $suspension = $group->suspensions()->whereDate('suspension_date', $date)->first();

        abort_unless($suspension, 404, 'No hay una suspensión registrada para esa fecha.');

        $students = $group->students()
            ->where('active', 1)
            ->orderBy('full_name')
            ->get();

        $notifiedStudentIds = WhatsappNotification::where('group_id', $group->id)
            ->whereDate('attendance_date', $date)
            ->where('status', 'suspendida')
            ->pluck('student_id');

        return view('attendance.suspend-notify', compact(
            'group', 'date', 'suspension', 'students', 'notifiedStudentIds'
        ));
    }

    /**
     * Cancela (deshace) una suspensión registrada por error: borra el registro de
     * suspensión y las marcas de "suspendida" que se generaron automáticamente ese día,
     * para que el docente pueda volver a pasar asistencia normalmente.
     * No borra el historial de avisos de WhatsApp ya enviados a los encargados.
     */
    public function destroy(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        $suspension = $group->suspensions()->whereDate('suspension_date', $date)->first();

        abort_unless($suspension, 404, 'No hay una suspensión registrada para esa fecha.');

        Attendance::where('group_id', $group->id)
            ->whereNull('subject_id')
            ->whereDate('attendance_date', $date)
            ->where('status', 'suspendida')
            ->delete();

        $suspension->delete();

        return redirect()
            ->route('attendance.create', ['group' => $group->id, 'date' => $date])
            ->with('success', 'Suspensión cancelada. Ya podés pasar asistencia normalmente este día.');
    }
}
