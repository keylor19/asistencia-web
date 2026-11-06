<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Evaluaciones de cada componente de la nota (pruebas, trabajo cotidiano,
 * proyectos y tareas): su valor en la nota, qué se realizó y la nota de cada estudiante.
 */
class EvaluationController extends Controller
{
    public function index(Request $request, string $category)
    {
        $groups = Auth::user()->groups;
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();

        $groupId = $request->query('group'); // vacío = todavía no escoge grupo
        $subjectId = $request->query('subject');

        $group = null;
        $evaluations = collect();

        if ($groupId) {
            $group = Group::findOrFail($groupId);
            $this->authorize('access', $group);

            $evaluations = $group->evaluations()
                ->with('subject')
                ->withCount(['scores' => fn ($q) => $q->whereNotNull('points')])
                ->where('category', $category)
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->orderByDesc('evaluation_date')
                ->get()
                ->each(function ($evaluation) {
                    $evaluation->semester = Semester::numberFor(Auth::id(), $evaluation->evaluation_date);
                });
        }

        $categoryInfo = Evaluation::CATEGORIES[$category];
        $studentsCount = $group ? $group->students()->where('active', 1)->count() : 0;

        // Cuánto vale cada evaluación en la nota, calculado por semestre, y avisos
        // cuando los valores asignados se pasan del valor del componente.
        $effectiveWeights = [];
        $weightWarnings = [];

        if ($group) {
            $categoryWeight = (float) $group->{$categoryInfo['weight_column']};

            $evaluations->whereNotNull('semester')
                ->groupBy(fn ($e) => $e->evaluation_date->year . '-' . $e->semester)
                ->each(function ($semesterEvaluations) use ($categoryWeight, &$effectiveWeights, &$weightWarnings) {
                    $effectiveWeights += Evaluation::effectiveWeights($semesterEvaluations, $categoryWeight);

                    $assigned = $semesterEvaluations->sum('weight');
                    if ($assigned > $categoryWeight) {
                        $first = $semesterEvaluations->first();
                        $weightWarnings[] = [
                            'semester' => $first->semester === 1 ? 'I' : 'II',
                            'year' => $first->evaluation_date->year,
                            'assigned' => $assigned,
                        ];
                    }
                });
        }

        return view('evaluations.index', compact(
            'category', 'categoryInfo', 'groups', 'group', 'groupId', 'subjects', 'subjectId',
            'evaluations', 'studentsCount', 'effectiveWeights', 'weightWarnings'
        ));
    }

    public function store(Request $request, string $category)
    {
        $validated = $this->validateDetails($request);
        $validated += $request->validate(['group_id' => 'required|exists:student_groups,id']);

        $group = Group::findOrFail($validated['group_id']);
        $this->authorize('access', $group);

        $evaluation = Evaluation::create($validated + [
            'category' => $category,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluación creada. Ahora podés registrar la nota de cada estudiante.');
    }

    /**
     * Formulario para editar la evaluación y registrar los puntos de cada estudiante.
     */
    public function show(Evaluation $evaluation)
    {
        $this->authorize('access', $evaluation->group);

        $students = $evaluation->group->students()
            ->where('active', 1)
            ->orderBy('full_name')
            ->get();

        $scores = $evaluation->scores->keyBy('student_id');
        $subjects = Subject::where('user_id', Auth::id())->orderBy('name')->get();
        $categoryInfo = Evaluation::CATEGORIES[$evaluation->category];
        $autoWeight = $this->autoWeightOf($evaluation, $categoryInfo);

        return view('evaluations.show', compact('evaluation', 'students', 'scores', 'subjects', 'categoryInfo', 'autoWeight'));
    }

    /**
     * Lo que valdría la evaluación en la nota si se deja sin valor propio: lo que
     * sobra del componente repartido entre las evaluaciones "auto" del mismo
     * semestre y subárea. Null si la fecha no cae en ningún semestre.
     */
    private function autoWeightOf(Evaluation $evaluation, array $categoryInfo): ?float
    {
        $semester = Semester::numberFor(Auth::id(), $evaluation->evaluation_date);

        if ($semester === null) {
            return null;
        }

        [$start, $end] = Semester::datesFor(Auth::id(), $evaluation->evaluation_date->year)[$semester];

        $siblings = Evaluation::where('group_id', $evaluation->group_id)
            ->where('category', $evaluation->category)
            ->where('subject_id', $evaluation->subject_id)
            ->whereBetween('evaluation_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get()
            ->each(function ($sibling) use ($evaluation) {
                if ($sibling->is($evaluation)) {
                    $sibling->weight = null;
                }
            });

        $weights = Evaluation::effectiveWeights($siblings, (float) $evaluation->group->{$categoryInfo['weight_column']});

        return $weights[$evaluation->id] ?? null;
    }

    public function update(Request $request, Evaluation $evaluation)
    {
        $this->authorize('access', $evaluation->group);

        $validated = $this->validateDetails($request);

        $request->validate([
            'points' => 'nullable|array',
            'points.*' => 'nullable|numeric|min:0|max:' . $validated['max_points'],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $studentIds = $evaluation->group->students()->pluck('id')->all();

        DB::transaction(function () use ($evaluation, $validated, $request, $studentIds) {
            $evaluation->update($validated);

            foreach ($request->input('points', []) as $studentId => $points) {
                // Solo estudiantes del grupo de la evaluación.
                if (! in_array((int) $studentId, $studentIds, true)) {
                    continue;
                }

                EvaluationScore::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'student_id' => $studentId],
                    [
                        'points' => $points === null || $points === '' ? null : $points,
                        'notes' => $request->input("notes.$studentId"),
                    ]
                );
            }
        });

        return redirect()
            ->route('evaluations.show', $evaluation)
            ->with('success', 'Notas guardadas correctamente.');
    }

    public function destroy(Evaluation $evaluation)
    {
        $this->authorize('access', $evaluation->group);

        $category = $evaluation->category;
        $groupId = $evaluation->group_id;

        $evaluation->delete();

        return redirect()
            ->route('evaluations.index', ['category' => $category, 'group' => $groupId])
            ->with('success', 'Evaluación eliminada.');
    }

    /**
     * Guarda cuánto vale este componente en la nota del grupo (ej. pruebas = 35%).
     */
    public function updateWeight(Request $request, string $category, Group $group)
    {
        $this->authorize('access', $group);

        $validated = $request->validate([
            'weight' => 'required|numeric|min:0|max:100',
        ]);

        $group->update([Evaluation::CATEGORIES[$category]['weight_column'] => $validated['weight']]);

        return redirect()
            ->route('evaluations.index', array_merge(
                $request->only(['subject']),
                ['category' => $category, 'group' => $group->id]
            ))
            ->with('success', 'Valor de ' . mb_strtolower(Evaluation::CATEGORIES[$category]['label']) . ' actualizado.');
    }

    private function validateDetails(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'evaluation_date' => 'required|date',
            'max_points' => 'required|numeric|min:0.01|max:9999',
            'weight' => 'nullable|numeric|min:0|max:100',
            'subject_id' => [
                'nullable',
                Rule::exists('subjects', 'id')->where('user_id', Auth::id()),
            ],
        ]);
    }
}
