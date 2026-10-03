<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsappNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_the_suspend_classes_form_for_their_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}/suspender");

        $response->assertOk();
    }

    public function test_teacher_cannot_view_suspend_form_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->get("/asistencia/{$otherGroup->id}/suspender");

        $response->assertForbidden();
    }

    public function test_teacher_can_register_a_suspension_and_it_marks_all_active_students(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $activeStudent = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);
        $inactiveStudent = Student::create(['group_id' => $group->id, 'full_name' => 'Beto Solano', 'active' => 0]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $response->assertRedirect(route('suspensions.notify', ['group' => $group->id, 'date' => '2026-01-15']));

        $this->assertDatabaseHas('class_suspensions', [
            'group_id' => $group->id,
            'reason' => 'Fuertes lluvias',
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $activeStudent->id,
            'status' => 'suspendida',
            'notes' => 'Suspensión de clases: Fuertes lluvias',
        ]);

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $inactiveStudent->id,
        ]);
    }

    public function test_registering_a_suspension_again_updates_the_reason_instead_of_duplicating(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Falta de agua potable',
        ]);

        $this->assertDatabaseCount('class_suspensions', 1);
        $this->assertDatabaseHas('class_suspensions', ['reason' => 'Falta de agua potable']);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'status' => 'suspendida',
            'notes' => 'Suspensión de clases: Falta de agua potable',
        ]);
    }

    public function test_teacher_cannot_register_a_suspension_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->post("/asistencia/{$otherGroup->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('class_suspensions', ['group_id' => $otherGroup->id]);
    }

    public function test_notify_page_lists_active_students_with_their_guardian_contact(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        Student::create([
            'group_id' => $group->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
        ]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}/suspender/avisar?date=2026-01-15");

        $response->assertOk();
        $response->assertSee('Ana Pérez');
        $response->assertSee('María Pérez');
        $response->assertSee('Fuertes lluvias');
    }

    public function test_notify_page_returns_404_when_there_is_no_suspension_for_that_date(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}/suspender/avisar?date=2026-01-15");

        $response->assertNotFound();
    }

    public function test_teacher_can_log_a_whatsapp_suspension_notification(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create([
            'group_id' => $group->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_phone' => '8888-1234',
        ]);

        $response = $this->actingAs($teacher)->postJson('/asistencia/notificar', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'date' => '2026-01-15',
            'status' => 'suspendida',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_notifications', [
            'student_id' => $student->id,
            'status' => 'suspendida',
        ]);
    }

    public function test_attendance_page_shows_a_banner_and_hides_the_roll_call_form_when_day_is_suspended(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}?date=2026-01-15");

        $response->assertOk();
        $response->assertSee('fueron');
        $response->assertSee('Fuertes lluvias');
        $response->assertDontSee('Guardar asistencia');
    }

    public function test_teacher_can_cancel_a_suspension_registered_by_mistake(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Me equivoqué de fecha',
        ]);

        $response = $this->actingAs($teacher)->delete("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
        ]);

        $response->assertRedirect(route('attendance.create', ['group' => $group->id, 'date' => '2026-01-15']));
        $this->assertDatabaseMissing('class_suspensions', ['group_id' => $group->id]);
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id, 'status' => 'suspendida']);

        // Al cancelarse, la pantalla de asistencia debe volver a mostrar el formulario normal.
        $response = $this->actingAs($teacher)->get("/asistencia/{$group->id}?date=2026-01-15");
        $response->assertDontSee('fueron');
    }

    public function test_cancelling_a_suspension_does_not_delete_whatsapp_notification_history(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $student = Student::create([
            'group_id' => $group->id,
            'full_name' => 'Ana Pérez',
            'active' => 1,
            'guardian_phone' => '8888-1234',
        ]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $this->actingAs($teacher)->postJson('/asistencia/notificar', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'date' => '2026-01-15',
            'status' => 'suspendida',
        ]);

        $this->actingAs($teacher)->delete("/asistencia/{$group->id}/suspender", ['date' => '2026-01-15']);

        $this->assertDatabaseHas('whatsapp_notifications', [
            'student_id' => $student->id,
            'status' => 'suspendida',
        ]);
    }

    public function test_cannot_cancel_a_suspension_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);
        $otherTeacher->groups()->attach($otherGroup->id);

        $this->actingAs($otherTeacher)->post("/asistencia/{$otherGroup->id}/suspender", [
            'date' => '2026-01-15',
            'reason' => 'Fuertes lluvias',
        ]);

        $response = $this->actingAs($teacher)->delete("/asistencia/{$otherGroup->id}/suspender", [
            'date' => '2026-01-15',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('class_suspensions', ['group_id' => $otherGroup->id]);
    }

    public function test_cancelling_when_there_is_no_suspension_returns_404(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->delete("/asistencia/{$group->id}/suspender", [
            'date' => '2026-01-15',
        ]);

        $response->assertNotFound();
    }
}
