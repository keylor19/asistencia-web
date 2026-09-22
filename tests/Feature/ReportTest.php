<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\WhatsappNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_report_for_own_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->get("/reportes?group={$group->id}");

        $response->assertOk();
    }

    public function test_teacher_cannot_view_report_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'nocturno']);

        $response = $this->actingAs($teacher)->get("/reportes?group={$otherGroup->id}");

        $response->assertForbidden();
    }

    /**
     * Regresión: el resumen antes agrupaba por nombre de estudiante, así que dos
     * estudiantes con el mismo nombre mezclaban sus conteos de asistencia en una sola fila.
     */
    public function test_report_summary_does_not_mix_students_with_the_same_name(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática']);

        $studentA = Student::create(['group_id' => $group->id, 'full_name' => 'José Rodríguez', 'active' => 1]);
        $studentB = Student::create(['group_id' => $group->id, 'full_name' => 'José Rodríguez', 'active' => 1]);

        Attendance::create([
            'student_id' => $studentA->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'presente',
        ]);

        Attendance::create([
            'student_id' => $studentB->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'ausente',
        ]);

        $response = $this->actingAs($teacher)->get(
            "/reportes?group={$group->id}&period=day&date=2026-01-15"
        );

        $response->assertOk();

        $summary = $response->viewData('summary');

        $this->assertCount(2, $summary, 'Cada estudiante debe tener su propia fila en el resumen.');

        $presentCounts = $summary->pluck('presente')->sort()->values();
        $absentCounts = $summary->pluck('ausente')->sort()->values();

        $this->assertSame([0, 1], $absentCounts->all());
        $this->assertSame([0, 1], $presentCounts->all());
    }

    public function test_teacher_can_view_detailed_report_for_a_student_in_their_group(): void
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

        Attendance::create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'ausente',
            'notes' => 'No se presentó',
        ]);

        WhatsappNotification::create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'ausente',
            'guardian_name' => 'María Pérez',
            'guardian_phone' => '8888-1234',
            'sent_at' => '2026-01-15 08:30:00',
        ]);

        $response = $this->actingAs($teacher)->get(
            route('reports.student', ['student' => $student->id, 'period' => 'week', 'date' => '2026-01-15'])
        );

        $response->assertOk();
        $response->assertSee('Ana Pérez');
        $response->assertSee('María Pérez');
        $response->assertSee('8888-1234');
        $response->assertSee('No se presentó');

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['ausente']);

        $notifications = $response->viewData('notifications');
        $this->assertCount(1, $notifications);
    }

    public function test_teacher_cannot_view_detailed_report_for_a_student_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);
        $student = Student::create(['group_id' => $otherGroup->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->get(route('reports.student', $student->id));

        $response->assertForbidden();
    }

    public function test_teacher_can_download_the_student_report_as_pdf(): void
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

        Attendance::create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'attendance_date' => '2026-01-15',
            'status' => 'tardia',
        ]);

        $response = $this->actingAs($teacher)->get(
            route('reports.student.pdf', ['student' => $student->id, 'period' => 'week', 'date' => '2026-01-15'])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_teacher_cannot_download_pdf_report_for_a_student_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);
        $student = Student::create(['group_id' => $otherGroup->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->get(route('reports.student.pdf', $student->id));

        $response->assertForbidden();
    }
}
