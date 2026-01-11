<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSchedule extends Model
{
    protected $fillable = [
        'service_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
            'is_available' => 'boolean',
        ];
    }

    /**
     * Get the service that owns the schedule.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
