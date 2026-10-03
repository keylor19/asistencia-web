<?php

namespace Tests\Feature;

use App\Models\ClassNote;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_attendance_with_a_class_note_persists_it(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        $student = Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        $response = $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'presente'],
            'class_note' => 'Se trabajó en instalación de XAMPP',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('class_notes', [
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'content' => 'Se trabajó en instalación de XAMPP',
            'user_id' => $teacher->id,
        ]);
    }

    public function test_resaving_attendance_updates_the_existing_note_instead_of_duplicating(): void
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
            'class_note' => 'Primer avance',
        ]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'presente'],
            'class_note' => 'Avance final del día',
        ]);

        $this->assertDatabaseCount('class_notes', 1);
        $this->assertDatabaseHas('class_notes', ['content' => 'Avance final del día']);
    }

    public function test_saving_attendance_with_an_empty_note_removes_the_previous_one(): void
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
            'class_note' => 'Algo que luego se borra',
        ]);

        $this->actingAs($teacher)->post("/asistencia/{$group->id}", [
            'date' => '2026-01-15',
            'subject_id' => $subject->id,
            'attendance' => [$student->id => 'presente'],
            'class_note' => '',
        ]);

        $this->assertDatabaseCount('class_notes', 0);
    }

    public function test_class_note_appears_in_the_attendance_page_and_the_daily_pdf(): void
    {
        $teacher = User::factory()->create();
        $group = Group::create(['name' => 'Décimo A', 'shift' => 'diurno']);
        $teacher->groups()->attach($group->id);
        $subject = Subject::create(['name' => 'Matemática', 'user_id' => $teacher->id]);
        Student::create(['group_id' => $group->id, 'full_name' => 'Ana Pérez', 'active' => 1]);

        ClassNote::create([
            'group_id' => $group->id,
            'subject_id' => $subject->id,
            'user_id' => $teacher->id,
            'note_date' => '2026-01-15',
            'content' => 'Se trabajó en configuración de servidor local',
        ]);

        $pageResponse = $this->actingAs($teacher)->get(
            "/asistencia/{$group->id}?date=2026-01-15&subject={$subject->id}"
        );
        $pageResponse->assertOk();
        $pageResponse->assertSee('Se trabajó en configuración de servidor local');

        $pdfResponse = $this->actingAs($teacher)->get(
            "/asistencia/{$group->id}/pdf?date=2026-01-15&subject={$subject->id}"
        );
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
    }
}
