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
        Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
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
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
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

    public function test_teacher_can_record_how_many_lessons_a_student_missed(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'ausente'],
            'lessons' => [$student->id => 3],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => 'ausente',
            'lessons' => 3,
        ]);
    }

    public function test_lessons_cannot_exceed_the_groups_lessons_per_day(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno', 'type' => 'tecnico', 'lessons_per_day' => 8]);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'ausente'],
            'lessons' => [$student->id => 9],
        ]);

        $response->assertSessionHasErrors('lessons.' . $student->id);
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    public function test_lessons_is_ignored_when_status_is_presente(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'presente'],
            'lessons' => [$student->id => 5],
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => 'presente',
            'lessons' => null,
        ]);
    }

    /**
     * Regresión: al re-marcar la asistencia del mismo día/subárea debe actualizar el
     * registro existente, no intentar crear uno duplicado.
     */
    public function test_teacher_can_update_attendance_already_marked_for_the_same_day(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'presente'],
        ]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'ausente'],
            'notes' => [$student->id => 'No llegó'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => 'ausente',
            'notes' => 'No llegó',
        ]);
    }

    public function test_teacher_cannot_store_attendance_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
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
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
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

    public function test_teacher_can_download_the_daily_attendance_pdf_for_their_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $present = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);
        $unregistered = Student::create(['group_id' => $group->id, 'full_name' => 'Beto Solano', 'active' => 1]);

        Attendance::create([
            'student_id' => $present->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'tardia',
            'notes' => 'Llegó tarde',
        ]);

        $response = $this->actingAs($teacher)->get(
            "/asistencia/{$group->id}/pdf?date=2026-01-15&subject={$subject->id}"
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_cannot_download_daily_pdf_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->get("/asistencia/{$otherGroup->id}/pdf");

        $response->assertForbidden();
    }

    /**
     * Escenario real: dos docentes distintos comparten el mismo grupo (cada uno da una
     * subárea diferente al mismo grupo de estudiantes). Cada uno debe ver únicamente
     * sus propias subáreas al pasar lista, nunca las del otro docente.
     */
    public function test_teacher_only_sees_their_own_subjects_when_marking_attendance_for_a_shared_group(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacherA->groups()->attach($group->id);
        $teacherB->groups()->attach($group->id);

        Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);
        Subject::create(['name' => 'Inglés', 'user_id' => $teacherB->id]);

        $response = $this->actingAs($teacherB)->get("/asistencia/{$group->id}");

        $response->assertOk();
        $response->assertSee('Inglés');
        $response->assertDontSee('Matemática');
    }

    public function test_teacher_cannot_store_attendance_using_another_teachers_subject_in_a_shared_group(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacherA->groups()->attach($group->id);
        $teacherB->groups()->attach($group->id);

        $subjectA = Subject::create(['name' => 'Matemática', 'user_id' => $teacherA->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacherB)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subjectA->id,
            'attendance' => [
                $student->id => 'presente',
            ],
        ]);

        $response->assertSessionHasErrors('subject_id');
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }
}
