<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_group_defaults_to_tecnico_with_eight_lessons(): void
    {
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);

        // Los valores por defecto los aplica la base de datos en el INSERT, así que hay
        // que recargar el modelo para verlos (el objeto en memoria no los refleja solo).
        $group->refresh();

        $this->assertSame('tecnico', $group->type);
        $this->assertSame(8, $group->lessons_per_day);
    }

    public function test_teacher_can_view_the_edit_form_for_their_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->get("/grupos/{$group->id}/editar");

        $response->assertOk();
    }

    public function test_teacher_cannot_view_edit_form_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->get("/grupos/{$otherGroup->id}/editar");

        $response->assertForbidden();
    }

    public function test_teacher_can_update_group_type_and_lessons_per_day(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->put("/grupos/{$group->id}", [
            'type' => 'academico',
            'lessons_per_day' => 12,
        ]);

        $response->assertRedirect(route('groups.index'));
        $this->assertDatabaseHas('student_groups', [
            'id' => $group->id,
            'type' => 'academico',
            'lessons_per_day' => 12,
        ]);
    }

    public function test_cannot_update_group_with_an_invalid_type(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->put("/grupos/{$group->id}", [
            'type' => 'otro-invalido',
            'lessons_per_day' => 8,
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_teacher_cannot_update_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->put("/grupos/{$otherGroup->id}", [
            'type' => 'academico',
            'lessons_per_day' => 12,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('student_groups', ['id' => $otherGroup->id, 'type' => 'tecnico']);
    }
}
