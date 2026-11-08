<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualGrade extends Model
{
    protected $fillable = ['student_id', 'subject_id', 'user_id', 'year', 'semester', 'grade'];

    protected $casts = [
        'grade' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
