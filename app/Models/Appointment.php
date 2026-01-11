<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Orchid\Screen\AsSource;

class Appointment extends Model
{
    use HasFactory, AsSource, SoftDeletes;

    protected $fillable = [
        'service_id',
        'start_time',
        'end_time',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the service that owns the appointment.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }


    /**
     * Get the participants for the appointment.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }
}
