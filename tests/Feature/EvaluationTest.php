<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function teacherWithGroup(): array
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        return [$teacher, $group];
    }

    public function test_teacher_can_view_each_evaluation_category_for_own_group(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();

        foreach (array_keys(Evaluation::CATEGORIES) as $category) {
            $this->actingAs($teacher)
                ->get(route('evaluations.index', ['category' => $category, 'group' => $group->id]))
                ->assertOk()
                ->assertSee(Evaluation::CATEGORIES[$category]['label']);
        }
    }

    public function test_pages_ask_to_choose_a_group_instead_of_opening_the_first_one(): void
    {
        [$teacher] = $this->teacherWithGroup();

        $pages = [
            route('evaluations.index', 'pruebas'),
            route('grades.index'),
            route('reports.index'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($teacher)->get($url);

            $response->assertOk();
            $response->assertSee('Escoger grupo');
            $response->assertSee('Escogé un grupo');
            $this->assertNull($response->viewData('group'));
        }
    }

    public function test_unknown_category_returns_not_found(): void
    {
        [$teacher] = $this->teacherWithGroup();

        $this->actingAs($teacher)->get('/evaluaciones/examenes')->assertNotFound();
    }

    public function test_teacher_cannot_view_evaluations_of_a_group_that_is_not_theirs(): void
    {
        [$teacher] = $this->teacherWithGroup();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);

        $this->actingAs($teacher)
            ->get(route('evaluations.index', ['category' => 'pruebas', 'group' => $otherGroup->id]))
            ->assertForbidden();
    }

    public function test_teacher_can_create_an_evaluation_with_what_students_did(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();

        $response = $this->actingAs($teacher)->post(route('evaluations.store', 'tareas'), [
            'group_id' => $group->id,
            'name' => 'Tarea 1',
            'description' => 'Maqueta de una página con HTML y CSS',
            'evaluation_date' => '2026-03-10',
            'max_points' => 20,
        ]);

        $evaluation = Evaluation::first();

        $response->assertRedirect(route('evaluations.show', $evaluation));
        $this->assertSame('tareas', $evaluation->category);
        $this->assertSame('Maqueta de una página con HTML y CSS', $evaluation->description);
        $this->assertSame($teacher->id, $evaluation->user_id);
    }

    public function test_teacher_cannot_create_an_evaluation_for_a_group_that_is_not_theirs(): void
    {
        [$teacher] = $this->teacherWithGroup();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);

        $this->actingAs($teacher)->post(route('evaluations.store', 'pruebas'), [
            'group_id' => $otherGroup->id,
            'name' => 'Prueba 1',
            'evaluation_date' => '2026-03-10',
            'max_points' => 50,
        ])->assertForbidden();

        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_teacher_can_save_scores_and_students_of_other_groups_are_ignored(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);

        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);
        $luis = Student::create(['group_id' => $group->id, 'full_name' => 'Luis Mora', 'active' => 1]);
        $outsider = Student::create(['group_id' => $otherGroup->id, 'full_name' => 'Otro', 'active' => 1]);

        $evaluation = Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => 'pruebas',
            'name' => 'Prueba 1', 'evaluation_date' => '2026-03-10', 'max_points' => 50,
        ]);

        $response = $this->actingAs($teacher)->put(route('evaluations.update', $evaluation), [
            'name' => 'Prueba 1',
            'evaluation_date' => '2026-03-10',
            'max_points' => 50,
            'points' => [$ana->id => 45.5, $luis->id => '', $outsider->id => 50],
            'notes' => [$ana->id => 'Muy bien'],
        ]);

        $response->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertEquals(45.5, EvaluationScore::where('student_id', $ana->id)->value('points'));
        $this->assertSame('Muy bien', EvaluationScore::where('student_id', $ana->id)->value('notes'));
        $this->assertNull(EvaluationScore::where('student_id', $luis->id)->value('points'));
        $this->assertDatabaseMissing('evaluation_scores', ['student_id' => $outsider->id]);
    }

    public function test_grading_page_knows_how_much_the_evaluation_is_worth_in_the_grade(): void
    {
        [$teacher, $group] = $this->teacherWithGroup(); // tareas vale 10% por defecto
        $create = fn (string $name, ?float $weight) => Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => 'tareas',
            'name' => $name, 'evaluation_date' => '2026-03-10', 'max_points' => 100, 'weight' => $weight,
        ]);

        $fixed = $create('Tarea 1', 4);
        $auto = $create('Tarea 2', null);
        $create('Tarea 3', null);

        // Lo que sobra (10 - 4 = 6) se reparte entre las dos sin valor → 3 cada una.
        $this->actingAs($teacher)->get(route('evaluations.show', $auto))
            ->assertOk()
            ->assertViewHas('autoWeight', 3.0);

        // Si a Tarea 1 le borraran el valor, quedarían tres "auto" → 10 / 3.
        $this->actingAs($teacher)->get(route('evaluations.show', $fixed))
            ->assertViewHas('autoWeight', round(10 / 3, 2));

        // Fuera de los semestres no se sabe cuánto vale.
        $outside = $create('Tarea vieja', null);
        $outside->update(['evaluation_date' => '2025-03-10']);
        Semester::create(['user_id' => $teacher->id, 'year' => 2025, 'number' => 1, 'start_date' => '2025-04-01', 'end_date' => '2025-06-30']);

        $this->actingAs($teacher)->get(route('evaluations.show', $outside))
            ->assertViewHas('autoWeight', null);
    }

    public function test_scores_above_the_total_points_are_rejected(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $evaluation = Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => 'pruebas',
            'name' => 'Prueba 1', 'evaluation_date' => '2026-03-10', 'max_points' => 50,
        ]);

        $this->actingAs($teacher)->put(route('evaluations.update', $evaluation), [
            'name' => 'Prueba 1',
            'evaluation_date' => '2026-03-10',
            'max_points' => 50,
            'points' => [$ana->id => 60],
        ])->assertSessionHasErrors('points.' . $ana->id);

        $this->assertDatabaseCount('evaluation_scores', 0);
    }

    public function test_teacher_cannot_grade_an_evaluation_of_another_group(): void
    {
        [$teacher] = $this->teacherWithGroup();
        $otherTeacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);

        $evaluation = Evaluation::create([
            'group_id' => $otherGroup->id, 'user_id' => $otherTeacher->id, 'category' => 'pruebas',
            'name' => 'Prueba 1', 'evaluation_date' => '2026-03-10', 'max_points' => 50,
        ]);

        $this->actingAs($teacher)->get(route('evaluations.show', $evaluation))->assertForbidden();
        $this->actingAs($teacher)->delete(route('evaluations.destroy', $evaluation))->assertForbidden();
        $this->assertDatabaseCount('evaluations', 1);
    }

    public function test_teacher_can_configure_the_weight_of_a_category(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();

        $this->actingAs($teacher)
            ->put(route('evaluations.weight', ['category' => 'proyectos', 'group' => $group->id]), ['weight' => 25])
            ->assertRedirect(route('evaluations.index', ['category' => 'proyectos', 'group' => $group->id]));

        $this->assertEquals(25, $group->fresh()->projects_weight);
    }

    public function test_teacher_can_delete_an_evaluation(): void
    {
        [$teacher, $group] = $this->teacherWithGroup();

        $evaluation = Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => 'tareas',
            'name' => 'Tarea 1', 'evaluation_date' => '2026-03-10', 'max_points' => 10,
        ]);

        $this->actingAs($teacher)
            ->delete(route('evaluations.destroy', $evaluation))
            ->assertRedirect(route('evaluations.index', ['category' => 'tareas', 'group' => $group->id]));

        $this->assertDatabaseCount('evaluations', 0);
    }
}
