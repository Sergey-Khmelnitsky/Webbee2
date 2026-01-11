<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

class Service extends Model
{
    use HasFactory, AsSource;
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the configuration for the service.
     */
    public function configuration(): HasOne
    {
        return $this->hasOne(ServiceConfiguration::class);
    }

    /**
     * Get the schedules for the service.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    /**
     * Get the breaks for the service.
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(ServiceBreak::class);
    }

    /**
     * Get the holidays for the service.
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(ServiceHoliday::class);
    }

    /**
     * Get the appointments for the service.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
