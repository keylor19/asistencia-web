<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Auth::user()->groups()->withCount('students')->get();

        return view('groups.index', compact('groups'));
    }

    /**
     * Formulario para configurar el tipo de grupo (técnico/académico) y cuántas
     * lecciones tiene un día completo, usado como tope al registrar lecciones
     * perdidas por ausencia o tardía.
     */
    public function edit(Group $group)
    {
        $this->authorize('access', $group);

        return view('groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('access', $group);

        $validated = $request->validate([
            'type' => 'required|in:tecnico,academico',
            'lessons_per_day' => 'required|integer|min:1|max:20',
        ]);

        $group->update($validated);

        return redirect()->route('groups.index')->with('success', 'Grupo actualizado correctamente.');
    }
}
