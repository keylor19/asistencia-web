<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Evaluation extends Model
{
    /**
     * Componentes de la nota. La clave es la que se guarda en `category` y se usa
     * en la URL; `weight_column` es la columna del grupo con su valor en la nota.
     */
    public const CATEGORIES = [
        'pruebas' => ['label' => 'Pruebas', 'singular' => 'prueba', 'weight_column' => 'tests_weight'],
        'cotidiano' => ['label' => 'Trabajo cotidiano', 'singular' => 'trabajo cotidiano', 'weight_column' => 'daily_work_weight'],
        'proyectos' => ['label' => 'Proyectos', 'singular' => 'proyecto', 'weight_column' => 'projects_weight'],
        'tareas' => ['label' => 'Tareas', 'singular' => 'tarea', 'weight_column' => 'homework_weight'],
    ];

    protected $fillable = [
        'group_id', 'subject_id', 'user_id', 'category', 'name', 'description', 'evaluation_date', 'max_points', 'weight',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'max_points' => 'float',
        'weight' => 'float',
    ];

    /**
     * Valor real en la nota de cada evaluación de un mismo componente y semestre, como [id => valor].
     * Las que tienen valor propio lo usan; las que no, se reparten en partes iguales lo que
     * sobre del valor del componente. Ej. Pruebas 35%: una sola sin valor = 35%;
     * Prueba 1 = 15% y Prueba 2 sin valor = 20%.
     */
    public static function effectiveWeights(Collection $evaluations, float $categoryWeight): array
    {
        $assigned = $evaluations->whereNotNull('weight');
        $unassigned = $evaluations->whereNull('weight');

        $share = $unassigned->isEmpty()
            ? 0
            : max(0, $categoryWeight - $assigned->sum('weight')) / $unassigned->count();

        return $evaluations->mapWithKeys(fn ($evaluation) => [
            $evaluation->id => round($evaluation->weight ?? $share, 2),
        ])->all();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
