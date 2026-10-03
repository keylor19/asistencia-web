<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        return view('subjects.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('subjects')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
        ]);

        Subject::create([
            'name' => $validated['name'],
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('subjects.index')->with('success', 'Subárea agregada correctamente.');
    }

    public function edit(Subject $subject)
    {
        $this->authorize('access', $subject);

        return view('subjects.edit', compact('subject'));
    }

    public function update(Request $request, Subject $subject)
    {
        $this->authorize('access', $subject);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('subjects')->where(fn ($query) => $query->where('user_id', Auth::id()))->ignore($subject->id),
            ],
        ]);

        $subject->update($validated);

        return redirect()->route('subjects.index')->with('success', 'Subárea actualizada correctamente.');
    }

    public function destroy(Subject $subject)
    {
        $this->authorize('access', $subject);

        $subject->delete();

        return redirect()->route('subjects.index')->with('success', 'Subárea eliminada.');
    }
}
