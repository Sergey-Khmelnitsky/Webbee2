<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceConfiguration extends Model
{
    protected $fillable = [
        'service_id',
        'duration_minutes',
        'break_between_minutes',
        'max_concurrent_clients',
        'booking_advance_days',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'break_between_minutes' => 'integer',
            'max_concurrent_clients' => 'integer',
            'booking_advance_days' => 'integer',
        ];
    }

    /**
     * Get the service that owns the configuration.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
