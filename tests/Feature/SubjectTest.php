<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_only_sees_their_own_subjects(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);
        Subject::create(['name' => 'Inglés', 'user_id' => $teacherB->id]);

        $response = $this->actingAs($teacherA)->get('/subareas');

        $response->assertOk();
        $response->assertSee('Matemática');
        $response->assertDontSee('Inglés');
    }

    public function test_teacher_can_create_a_subject_with_the_same_name_another_teacher_already_uses(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);

        $response = $this->actingAs($teacherB)->post('/subareas', ['name' => 'Matemática']);

        $response->assertRedirect(route('subjects.index'));
        $this->assertDatabaseHas('subjects', ['name' => 'Matemática', 'user_id' => $teacherB->id]);
    }

    public function test_teacher_cannot_create_two_subjects_with_the_same_name_for_themselves(): void
    {
        $teacher = User::factory()->create();
        Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);

        $response = $this->actingAs($teacher)->post('/subareas', ['name' => 'Matemática']);

        $response->assertSessionHasErrors('name');
    }

    public function test_teacher_cannot_edit_another_teachers_subject(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);

        $response = $this->actingAs($teacherB)->get("/subareas/{$subject->id}/editar");
        $response->assertForbidden();

        $response = $this->actingAs($teacherB)->put("/subareas/{$subject->id}", ['name' => 'Hackeada']);
        $response->assertForbidden();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Matemática']);
    }

    public function test_teacher_cannot_delete_another_teachers_subject(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);

        $response = $this->actingAs($teacherB)->delete("/subareas/{$subject->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_teacher_can_edit_their_own_subject(): void
    {
        $teacher = User::factory()->create();
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);

        $response = $this->actingAs($teacher)->put("/subareas/{$subject->id}", ['name' => 'Matemática Avanzada']);

        $response->assertRedirect(route('subjects.index'));
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Matemática Avanzada']);
    }
}
