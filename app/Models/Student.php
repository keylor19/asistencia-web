<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'group_id', 'full_name', 'identification', 'active', 'guardian_name', 'guardian_phone',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function whatsappNotifications(): HasMany
    {
        return $this->hasMany(WhatsappNotification::class);
    }

    /**
     * Teléfono del encargado normalizado para enlaces de WhatsApp (wa.me),
     * con código de país 506 agregado si no viene incluido.
     */
    public function getWhatsappPhoneAttribute(): ?string
    {
        if (! $this->guardian_phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->guardian_phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '506') ? $digits : '506' . $digits;
    }
}