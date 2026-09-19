<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    protected $fillable = [
        'student_id', 'group_id', 'subject_id', 'user_id',
        'attendance_date', 'status', 'guardian_name', 'guardian_phone', 'sent_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
}
