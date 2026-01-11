<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBreak extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'start_time',
        'end_time',
        'day_of_week',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'string',
            'end_time' => 'string',
            'day_of_week' => 'integer',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Get the service that owns the break.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
