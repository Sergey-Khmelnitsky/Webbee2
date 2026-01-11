<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceHoliday extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'start_datetime',
        'end_datetime',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
        ];
    }

    /**
     * Get the service that owns the holiday.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
