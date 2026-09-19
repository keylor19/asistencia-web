<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_list_students_from_their_own_groups(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->get('/estudiantes');

        $response->assertOk();
        $response->assertSee('Ana Pérez');
    }

    public function test_teacher_can_register_a_student_with_guardian_whatsapp(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->post('/estudiantes', [
            'full_name' => 'Ana Pérez',
            'group_id' => $group->id,
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
        ]);

        $response->assertRedirect(route('students.index'));
        $this->assertDatabaseHas('students', [
            'full_name' => 'Ana Pérez',
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
        ]);
    }

    public function test_guardian_phone_is_normalized_for_whatsapp_with_costa_rica_country_code(): void
    {
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $student = Student::create([
            'group_id' => $group->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_phone' => '8888-1234',
        ]);

        $this->assertSame('50688881234', $student->whatsapp_phone);
    }

    public function test_whatsapp_phone_is_null_when_no_guardian_phone_registered(): void
    {
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->assertNull($student->whatsapp_phone);
    }

    public function test_teacher_cannot_register_a_student_into_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->post('/estudiantes', [
            'full_name' => 'Ana Pérez',
            'group_id' => $otherGroup->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('students', ['full_name' => 'Ana Pérez']);
    }

    public function test_teacher_can_deactivate_a_student_without_deleting_their_history(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->delete("/estudiantes/{$student->id}");

        $response->assertRedirect(route('students.index'));
        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => 0]);
    }
}
