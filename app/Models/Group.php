<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $table = 'student_groups'; // la tabla real se llama así, no "groups"

    protected $fillable = ['name', 'shift'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_group');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}