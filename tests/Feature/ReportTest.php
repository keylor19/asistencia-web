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
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);

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

    public function test_report_calculates_attendance_percentage_and_flags_students_below_the_minimum(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno', 'min_attendance_percentage' => 80]);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);

        $good = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);
        $bad = Student::create(['group_id' => $group->id, 'full_name' => 'Luis Mora', 'active' => 1]);

        // Ana: 3 presente, 1 justificada (no rebaja), 1 suspendida (no cuenta) → 4/4 = 100%
        // Luis: 1 presente, 1 justificada, 1 ausente, 1 tardía, 1 suspendida → 2/4 = 50%
        $statuses = [
            $good->id => ['presente', 'presente', 'presente', 'justificada', 'suspendida'],
            $bad->id => ['presente', 'justificada', 'ausente', 'tardia', 'suspendida'],
        ];

        foreach ($statuses as $studentId => $list) {
            foreach ($list as $i => $status) {
                Attendance::create([
                    'student_id' => $studentId,
                    'group_id' => $group->id,
                    'subject_id' => $subject->id,
                    'user_id' => $teacher->id,
                    'attendance_date' => '2026-01-' . (10 + $i),
                    'status' => $status,
                ]);
            }
        }

        $response = $this->actingAs($teacher)->get("/reportes?group={$group->id}&period=all");

        $response->assertOk();

        $summary = $response->viewData('summary')->keyBy('student_id');

        $this->assertSame(100.0, $summary[$good->id]['porcentaje']);
        $this->assertFalse($summary[$good->id]['en_riesgo']);
        $this->assertSame(50.0, $summary[$bad->id]['porcentaje']);
        $this->assertTrue($summary[$bad->id]['en_riesgo']);

        $this->assertSame(75.0, $response->viewData('groupPercentage'));
        $this->assertSame(1, $response->viewData('atRiskCount'));

        // Con el valor por defecto de 5%: 100% → 5, 50% → 2.5
        $this->assertSame(5.0, $summary[$good->id]['puntos']);
        $this->assertSame(2.5, $summary[$bad->id]['puntos']);
    }

    public function test_attendance_grade_is_proportional_to_the_configured_weight(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno', 'attendance_weight' => 10]);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // 3 presente + 1 ausente → 75% de asistencia → 7.5 de 10
        foreach (['presente', 'presente', 'presente', 'ausente'] as $i => $status) {
            Attendance::create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'subject_id' => $subject->id,
                'user_id' => $teacher->id,
                'attendance_date' => '2026-01-' . (10 + $i),
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($teacher)->get("/reportes?group={$group->id}&period=all");

        $response->assertOk();
        $this->assertSame(7.5, $response->viewData('summary')->first()['puntos']);
        $response->assertSee('7.5%');
    }

    public function test_report_can_be_grouped_by_semester_of_the_reference_year(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // I semestre: 1 presente + 1 ausente → 50%. II semestre: 2 presente → 100%.
        // El registro de 2025 no debe entrar en ningún semestre de 2026.
        $records = [
            '2026-03-10' => 'presente',
            '2026-06-30' => 'ausente',
            '2026-07-01' => 'presente',
            '2026-11-20' => 'presente',
            '2025-03-10' => 'ausente',
        ];

        foreach ($records as $date => $status) {
            Attendance::create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'subject_id' => $subject->id,
                'user_id' => $teacher->id,
                'attendance_date' => $date,
                'status' => $status,
            ]);
        }

        // Fecha de referencia día 31 a propósito: no debe correr el fin del I semestre a julio.
        $first = $this->actingAs($teacher)->get("/reportes?group={$group->id}&period=semester1&date=2026-03-31");
        $first->assertOk();
        $this->assertSame(50.0, $first->viewData('summary')->first()['porcentaje']);

        $second = $this->actingAs($teacher)->get("/reportes?group={$group->id}&period=semester2&date=2026-11-03");
        $second->assertOk();
        $this->assertSame(100.0, $second->viewData('summary')->first()['porcentaje']);
    }

    public function test_semesters_use_the_dates_configured_by_the_teacher(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->put(route('reports.semesters'), [
            'year' => 2026,
            'semester1_start' => '2026-02-09',
            'semester1_end' => '2026-07-03',
            'semester2_start' => '2026-07-20',
            'semester2_end' => '2026-12-11',
            'group' => $group->id,
            'period' => 'semester1',
            'date' => '2026-03-10',
        ]);

        $response->assertRedirect(route('reports.index', [
            'group' => $group->id, 'period' => 'semester1', 'date' => '2026-03-10',
        ]));

        // 02/07 cae en el I semestre configurado (con las fechas por defecto sería II).
        // 05/02 queda antes del inicio y no cuenta.
        $records = ['2026-02-05' => 'ausente', '2026-03-10' => 'presente', '2026-07-02' => 'ausente'];

        foreach ($records as $date => $status) {
            Attendance::create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'subject_id' => $subject->id,
                'user_id' => $teacher->id,
                'attendance_date' => $date,
                'status' => $status,
            ]);
        }

        $report = $this->actingAs($teacher)->get("/reportes?group={$group->id}&period=semester1&date=2026-03-10");

        $report->assertOk();
        $this->assertSame(50.0, $report->viewData('summary')->first()['porcentaje']);
        $this->assertSame('2026-07-03', $report->viewData('endDate')->format('Y-m-d'));
    }

    public function test_semester_dates_must_be_in_order(): void
    {
        $teacher = User::factory()->create();

        $response = $this->actingAs($teacher)->put(route('reports.semesters'), [
            'year' => 2026,
            'semester1_start' => '2026-02-09',
            'semester1_end' => '2026-07-03',
            'semester2_start' => '2026-06-01',
            'semester2_end' => '2026-12-11',
        ]);

        $response->assertSessionHasErrors('semester2_start');
        $this->assertDatabaseCount('semesters', 0);
    }

    public function test_teacher_can_configure_minimum_percentage_and_attendance_weight(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);

        $response = $this->actingAs($teacher)->put(route('reports.settings', $group->id), [
            'min_attendance_percentage' => 90,
            'attendance_weight' => 7.5,
            'period' => 'all',
        ]);

        $response->assertRedirect(route('reports.index', ['period' => 'all', 'group' => $group->id]));
        $group->refresh();
        $this->assertSame(90, $group->min_attendance_percentage);
        $this->assertEquals(7.5, $group->attendance_weight);
    }

    public function test_teacher_cannot_configure_percentage_for_a_group_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);

        $response = $this->actingAs($teacher)->put(route('reports.settings', $otherGroup->id), [
            'min_attendance_percentage' => 90,
            'attendance_weight' => 10,
        ]);

        $response->assertForbidden();
        $this->assertSame(80, $otherGroup->fresh()->min_attendance_percentage);
    }

    public function test_teacher_can_view_detailed_report_for_a_student_in_their_group(): void
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
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
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
