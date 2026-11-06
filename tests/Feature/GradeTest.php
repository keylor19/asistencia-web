<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    private function evaluation(Group $group, User $teacher, string $category, string $date, float $maxPoints): Evaluation
    {
        return Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => $category,
            'name' => ucfirst($category) . ' ' . $date, 'evaluation_date' => $date, 'max_points' => $maxPoints,
        ]);
    }

    private function score(Evaluation $evaluation, Student $student, ?float $points): void
    {
        EvaluationScore::create(['evaluation_id' => $evaluation->id, 'student_id' => $student->id, 'points' => $points]);
    }

    public function test_semester_grade_adds_what_each_student_gets_from_each_component(): void
    {
        $teacher = User::factory()->create();
        // Valores por defecto: asistencia 5, pruebas 35, cotidiano 30, proyectos 20, tareas 10.
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // Pruebas: 40/50 (80%) y 30/50 (60%) → promedio 70% → 70% de 35 = 24.5
        $p1 = $this->evaluation($group, $teacher, 'pruebas', '2026-03-10', 50);
        $p2 = $this->evaluation($group, $teacher, 'pruebas', '2026-05-10', 50);
        $this->score($p1, $ana, 40);
        $this->score($p2, $ana, 30);

        // Tareas: una con 10/10 y otra sin nota (cuenta 0) → 50% de 10 = 5
        $t1 = $this->evaluation($group, $teacher, 'tareas', '2026-04-01', 10);
        $this->evaluation($group, $teacher, 'tareas', '2026-04-08', 10);
        $this->score($t1, $ana, 10);

        // Una prueba del II semestre no debe contar en el I.
        $later = $this->evaluation($group, $teacher, 'pruebas', '2026-09-10', 50);
        $this->score($later, $ana, 0);

        // Asistencia: 3 presente + 1 ausente → 75% de 5 = 3.75
        foreach (['2026-03-02' => 'presente', '2026-03-03' => 'presente', '2026-03-04' => 'presente', '2026-03-05' => 'ausente'] as $date => $status) {
            Attendance::create([
                'student_id' => $ana->id, 'group_id' => $group->id, 'subject_id' => $subject->id,
                'user_id' => $teacher->id, 'attendance_date' => $date, 'status' => $status,
            ]);
        }

        $response = $this->actingAs($teacher)->get(route('grades.index', [
            'group' => $group->id, 'semester' => 1, 'year' => 2026,
        ]));

        $response->assertOk();

        $row = $response->viewData('rows')->first();

        $this->assertSame(3.75, $row['components']['asistencia']['points']);
        $this->assertSame(24.5, $row['components']['pruebas']['points']);
        $this->assertSame(5.0, $row['components']['tareas']['points']);
        $this->assertNull($row['components']['cotidiano']['points']);
        $this->assertNull($row['components']['proyectos']['points']);

        $this->assertSame(33.25, $row['total']);
        // Solo se ha evaluado asistencia (5) + pruebas (35) + tareas (10).
        $this->assertSame(50.0, $row['evaluated_weight']);
    }

    public function test_each_test_can_have_its_own_weight_within_the_category(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']); // Pruebas = 35%
        $teacher->groups()->attach($group->id);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // Prueba 1 vale 15% y sacó 100% → 15. Prueba 2 sin valor toma lo que sobra (20%) y sacó 50% → 10.
        $p1 = $this->evaluation($group, $teacher, 'pruebas', '2026-03-10', 50);
        $p1->update(['weight' => 15]);
        $p2 = $this->evaluation($group, $teacher, 'pruebas', '2026-05-10', 40);
        $this->score($p1, $ana, 50);
        $this->score($p2, $ana, 20);

        $response = $this->actingAs($teacher)->get(route('grades.index', ['group' => $group->id, 'semester' => 1, 'year' => 2026]));

        $pruebas = $response->viewData('rows')->first()['components']['pruebas'];
        $this->assertSame(25.0, $pruebas['points']);
        $this->assertSame(35.0, $pruebas['evaluated_weight']);
        $this->assertSame([15.0, 20.0], array_column($pruebas['items'], 'weight'));
    }

    public function test_every_category_accepts_several_evaluations_with_split_weights(): void
    {
        $teacher = User::factory()->create();
        // Valores: pruebas 35, cotidiano 30, proyectos 20, tareas 10.
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // En cada componente: una evaluación con valor propio de 5% y dos sin valor que se reparten el resto.
        // Ana saca 100% en todas, así que obtiene el valor completo del componente.
        foreach (['pruebas' => 35, 'cotidiano' => 30, 'proyectos' => 20, 'tareas' => 10] as $category => $categoryWeight) {
            $withWeight = $this->evaluation($group, $teacher, $category, '2026-03-02', 10);
            $withWeight->update(['weight' => 5]);
            $this->score($withWeight, $ana, 10);

            foreach (['2026-03-09', '2026-03-16'] as $date) {
                $this->score($this->evaluation($group, $teacher, $category, $date, 10), $ana, 10);
            }
        }

        $response = $this->actingAs($teacher)->get(route('grades.index', ['group' => $group->id, 'semester' => 1, 'year' => 2026]));
        $components = $response->viewData('rows')->first()['components'];

        foreach (['pruebas' => 35, 'cotidiano' => 30, 'proyectos' => 20, 'tareas' => 10] as $category => $categoryWeight) {
            $remainingShare = (float) ($categoryWeight - 5) / 2;

            $this->assertCount(3, $components[$category]['items']);
            $this->assertSame([5.0, $remainingShare, $remainingShare], array_column($components[$category]['items'], 'weight'));
            $this->assertEquals($categoryWeight, $components[$category]['points']);
        }
    }

    public function test_single_test_with_partial_weight_leaves_the_rest_pending(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        // Solo se ha hecho la Prueba 1 (15% de 35%): obtiene 12 de 15 y lo evaluado es 15.
        $p1 = $this->evaluation($group, $teacher, 'pruebas', '2026-03-10', 10);
        $p1->update(['weight' => 15]);
        $this->score($p1, $ana, 8);

        $response = $this->actingAs($teacher)->get(route('grades.index', ['group' => $group->id, 'semester' => 1, 'year' => 2026]));

        $pruebas = $response->viewData('rows')->first()['components']['pruebas'];
        $this->assertSame(12.0, $pruebas['points']);
        $this->assertSame(15.0, $pruebas['evaluated_weight']);
        $this->assertSame(80.0, $pruebas['percentage']);
    }

    public function test_grades_use_the_semester_dates_configured_by_the_teacher(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        Semester::create(['user_id' => $teacher->id, 'year' => 2026, 'number' => 1, 'start_date' => '2026-02-09', 'end_date' => '2026-07-03']);
        Semester::create(['user_id' => $teacher->id, 'year' => 2026, 'number' => 2, 'start_date' => '2026-07-20', 'end_date' => '2026-12-11']);

        // 02/07 cae en el I semestre configurado.
        $evaluation = $this->evaluation($group, $teacher, 'proyectos', '2026-07-02', 100);
        $this->score($evaluation, $ana, 100);

        $first = $this->actingAs($teacher)->get(route('grades.index', ['group' => $group->id, 'semester' => 1, 'year' => 2026]));
        $this->assertSame(20.0, $first->viewData('rows')->first()['components']['proyectos']['points']);

        $second = $this->actingAs($teacher)->get(route('grades.index', ['group' => $group->id, 'semester' => 2, 'year' => 2026]));
        $this->assertNull($second->viewData('rows')->first()['components']['proyectos']['points']);
    }

    public function test_teacher_can_view_the_grade_detail_of_a_student(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $evaluation = Evaluation::create([
            'group_id' => $group->id, 'user_id' => $teacher->id, 'category' => 'cotidiano',
            'name' => 'Práctica de formularios', 'description' => 'Formulario de registro con validación',
            'evaluation_date' => '2026-03-10', 'max_points' => 10,
        ]);
        $this->score($evaluation, $ana, 8);

        $response = $this->actingAs($teacher)->get(route('grades.student', [
            'student' => $ana->id, 'semester' => 1, 'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertSee('Práctica de formularios');
        $response->assertSee('Formulario de registro con validación');
        // 80% de 30 = 24
        $this->assertSame(24.0, $response->viewData('row')['components']['cotidiano']['points']);
    }

    public function test_teacher_cannot_view_grades_of_a_group_or_student_that_is_not_theirs(): void
    {
        $teacher = User::factory()->create();
        $otherGroup = Group::create(['name' => 'Undécimo B', 'shift' => 'diurno']);
        $student = Student::create(['group_id' => $otherGroup->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $this->actingAs($teacher)->get(route('grades.index', ['group' => $otherGroup->id]))->assertForbidden();
        $this->actingAs($teacher)->get(route('grades.student', $student->id))->assertForbidden();
        $this->actingAs($teacher)->get(route('grades.export', $otherGroup->id))->assertForbidden();
        $this->actingAs($teacher)->get(route('grades.group-pdf', $otherGroup->id))->assertForbidden();
        $this->actingAs($teacher)->get(route('grades.student-pdf', $student->id))->assertForbidden();
    }

    public function test_teacher_can_download_the_grade_breakdown_pdf_per_student_and_for_the_whole_group(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $math = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $web = Subject::create(['name' => 'Desarrollo web', 'user_id' => $teacher->id]);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'identification' => '209190261', 'active' => 1]);
        $luis = Student::create(['group_id' => $group->id, 'full_name' => 'Luis Mora', 'active' => 1]);

        $test = $this->evaluation($group, $teacher, 'pruebas', '2026-03-10', 50);
        $test->update(['subject_id' => $math->id]);
        $this->score($test, $ana, 40);

        $homework = $this->evaluation($group, $teacher, 'tareas', '2026-03-12', 10);
        $homework->update(['subject_id' => $web->id]);
        $this->score($homework, $ana, 10);

        $params = ['semester' => 1, 'year' => 2026];

        $single = $this->actingAs($teacher)->get(route('grades.student-pdf', ['student' => $ana->id] + $params));
        $single->assertOk();
        $single->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Notas Ana Perez I Semestre 2026.pdf', $single->headers->get('content-disposition'));

        $this->assertStringStartsWith('%PDF', $single->getContent());

        // Captura el HTML con el que se arma el PDF del grupo.
        $html = null;
        \Illuminate\Support\Facades\View::composer('grades.student-pdf', function ($view) use (&$html) {
            if ($html === null) {
                $html = ''; // evita que el render de abajo vuelva a entrar aquí
                $html = (clone $view)->render();
            }
        });

        $all = $this->actingAs($teacher)->get(route('grades.group-pdf', ['group' => $group->id] + $params));
        $all->assertOk();
        $all->assertHeader('content-type', 'application/pdf');

        // Una página por estudiante, cada una con el desglose de las dos subáreas.
        $this->assertSame(2, substr_count($html, '<div class="page">'));
        $this->assertSame(2, substr_count($html, '<h2>Matemática</h2>'));
        $this->assertSame(2, substr_count($html, '<h2>Desarrollo web</h2>'));
        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('Luis Mora', $html);
        // Ana: 40/50 → 80% de 35 = 28
        $this->assertStringContainsString('40
                                        / 50', $html);
        $this->assertStringContainsString('<td class="center">28%</td>', $html);
    }

    public function test_excel_has_one_sheet_per_subject_with_the_points_of_each_component(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $math = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $web = Subject::create(['name' => 'Desarrollo web', 'user_id' => $teacher->id]);
        Subject::create(['name' => 'Sin usar', 'user_id' => $teacher->id]);
        $ana = Student::create(['group_id' => $group->id, 'full_name' => 'SOZA HERNANDEZ ANA', 'identification' => '209190261', 'active' => 1]);

        // Matemática: prueba 40/50 → 80% de 35 = 28
        $test = $this->evaluation($group, $teacher, 'pruebas', '2026-03-10', 50);
        $test->update(['subject_id' => $math->id]);
        $this->score($test, $ana, 40);

        // Desarrollo web: tarea 10/10 → 10
        $homework = $this->evaluation($group, $teacher, 'tareas', '2026-03-12', 10);
        $homework->update(['subject_id' => $web->id]);
        $this->score($homework, $ana, 10);

        $response = $this->actingAs($teacher)->get(route('grades.export', [
            'group' => $group->id, 'semester' => 1, 'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Notas Decimo A I Semestre 2026.xlsx', $response->headers->get('content-disposition'));

        $zip = new \ZipArchive();
        $zip->open($response->getFile()->getPathname());

        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('name="Desarrollo web"', $workbook);
        $this->assertStringContainsString('name="Matemática"', $workbook);
        $this->assertStringNotContainsString('Sin usar', $workbook);

        // Hojas en orden alfabético: 1 = Desarrollo web, 2 = Matemática.
        $math = $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        foreach (['Id', 'Nombre', 'Trabajo cotidiano', 'Tareas', 'Prueba', 'Proyecto', 'Asistencia'] as $title) {
            $this->assertStringContainsString('<t xml:space="preserve">' . $title . '</t>', $math);
        }
        $this->assertStringContainsString('<c r="A2"><v>209190261</v></c>', $math);
        $this->assertStringContainsString('SOZA HERNANDEZ ANA', $math);
        $this->assertStringContainsString('<c r="E2"><v>28</v></c>', $math);
    }
}
