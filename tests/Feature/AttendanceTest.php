<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_attendance_form_for_own_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        Subject::create(['name' => 'Matemática']);
        Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}");

        $response->assertOk();
        $response->assertSee('Ana Pérez');
    }

    public function test_teacher_cannot_view_attendance_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->get("/asistencia/{$otherGroup->id}");

        $response->assertForbidden();
    }

    public function test_teacher_can_store_attendance_for_own_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática']);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [
                $student->id => 'tardia',
            ],
            'notes' => [
                $student->id => 'Llegó 10 minutos tarde',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => 'tardia',
            'notes' => 'Llegó 10 minutos tarde',
        ]);

        $attendance = Attendance::where('student_id', $student->id)->firstOrFail();
        $this->assertTrue($attendance->attendance_date->isSameDay('2026-01-15'));
    }

    public function test_teacher_cannot_store_attendance_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);
        $subject = Subject::create(['name' => 'Matemática']);
        $student = Student::create(['group_id' => $otherGroup->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$otherGroup->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [
                $student->id => 'ausente',
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    public function test_teacher_can_log_a_whatsapp_notification_click(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Undécimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática']);
        $student = Student::create([
            'group_id' => $group->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
        ]);

        $response = $this->actingAs($teacher)->postJson('/asistencia/notificar', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'date' => '2026-01-15',
            'status' => 'ausente',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_notifications', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'status' => 'ausente',
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
        ]);
    }

    public function test_cannot_log_a_whatsapp_notification_for_a_student_without_guardian_phone(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Undécimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->postJson('/asistencia/notificar', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'date' => '2026-01-15',
            'status' => 'ausente',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('whatsapp_notifications', ['student_id' => $student->id]);
    }

    public function test_teacher_cannot_log_a_whatsapp_notification_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);
        $student = Student::create([
            'group_id' => $otherGroup->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_phone' => '8888-1234',
        ]);

        $response = $this->actingAs($teacher)->postJson('/asistencia/notificar', [
            'student_id' => $student->id,
            'group_id' => $otherGroup->id,
            'date' => '2026-01-15',
            'status' => 'ausente',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('whatsapp_notifications', ['student_id' => $student->id]);
    }
}
