<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    /**
     * Lista los estudiantes de los grupos del docente, con filtro opcional por grupo.
     */
    public function index(Request $request)
    {
        $groups = Auth::user()->groups;
        $groupIds = $groups->pluck('id');

        $selectedGroup = $request->query('group');

        $query = Student::whereIn('group_id', $groupIds)->with('group');

        if ($selectedGroup) {
            $query->where('group_id', $selectedGroup);
        }

        $students = $query->orderBy('full_name')->get();

        return view('students.index', compact('students', 'groups', 'selectedGroup'));
    }

    /**
     * Muestra el formulario para agregar un estudiante nuevo.
     */
    public function create()
    {
        $groups = Auth::user()->groups;

        return view('students.create', compact('groups'));
    }

    /**
     * Guarda un estudiante nuevo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identification' => 'nullable|string|max:30',
            'group_id' => 'required|exists:student_groups,id',
            'guardian_name' => 'nullable|string|max:150',
            'guardian_phone' => 'nullable|regex:/^[0-9+\s-]{8,20}$/',
        ]);

        $this->authorize('access', Group::findOrFail($validated['group_id']));

        Student::create($validated);

        return redirect()->route('students.index')->with('success', 'Estudiante agregado correctamente.');
    }

    /**
     * Muestra el formulario para editar un estudiante existente.
     */
    public function edit(Student $student)
    {
        $this->authorize('access', $student->group);

        $groups = Auth::user()->groups;

        return view('students.edit', compact('student', 'groups'));
    }

    /**
     * Actualiza los datos de un estudiante.
     */
    public function update(Request $request, Student $student)
    {
        $this->authorize('access', $student->group);

        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identification' => 'nullable|string|max:30',
            'group_id' => 'required|exists:student_groups,id',
            'active' => 'required|boolean',
            'guardian_name' => 'nullable|string|max:150',
            'guardian_phone' => 'nullable|regex:/^[0-9+\s-]{8,20}$/',
        ]);

        $this->authorize('access', Group::findOrFail($validated['group_id']));

        $student->update($validated);

        return redirect()->route('students.index')->with('success', 'Estudiante actualizado correctamente.');
    }

    /**
     * Da de baja (desactiva) un estudiante sin borrar su historial de asistencia.
     */
    public function destroy(Student $student)
    {
        $this->authorize('access', $student->group);

        $student->update(['active' => 0]);

        return redirect()->route('students.index')->with('success', 'Estudiante dado de baja.');
    }
}
