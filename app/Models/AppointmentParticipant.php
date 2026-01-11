<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentParticipant extends Model
{
    protected $fillable = [
        'appointment_id',
        'first_name',
        'last_name',
        'email',
    ];

    /**
     * Get the appointment that owns the participant.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the full name of the participant.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
