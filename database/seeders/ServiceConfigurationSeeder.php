<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceBreak;
use App\Models\ServiceConfiguration;
use App\Models\ServiceHoliday;
use App\Models\ServiceSchedule;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menHaircut = Service::where('name', 'Men Haircut')->first();
        $womenHaircut = Service::where('name', 'Women Haircut')->first();

        if (!$menHaircut || !$womenHaircut) {
            $this->command->error('Services not found. Please run ServiceSeeder first.');
            return;
        }

        // Men Haircut Configuration
        $this->configureMenHaircut($menHaircut);

        // Women Haircut Configuration
        $this->configureWomenHaircut($womenHaircut);
    }

    private function configureMenHaircut(Service $service): void
    {
        // Configuration
        ServiceConfiguration::updateOrCreate(
            ['service_id' => $service->id],
            [
                'duration_minutes' => 30, // appointment duration
                'break_between_minutes' => 5,
                'slot_interval_minutes' => 10, // slots every 10 minutes
                'max_concurrent_clients' => 3,
                'booking_advance_days' => 7,
            ]
        );

        // Schedules: Monday to Friday 08:00-20:00, Saturday 10:00-22:00, Sunday off
        $schedules = [
            ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '20:00'], // Monday
            ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '20:00'], // Tuesday
            ['day_of_week' => 3, 'start_time' => '08:00', 'end_time' => '20:00'], // Wednesday
            ['day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '20:00'], // Thursday
            ['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '20:00'], // Friday
            ['day_of_week' => 6, 'start_time' => '10:00', 'end_time' => '22:00'], // Saturday
            // Sunday (0) - off, no schedule
        ];

        foreach ($schedules as $schedule) {
            ServiceSchedule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => $schedule['day_of_week'],
                ],
                [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'is_available' => true,
                ]
            );
        }

        // Breaks: lunch 12:00-13:00, cleaning 15:00-16:00 (for all days except Sunday)
        $breaks = [
            ['name' => 'Lunch Break', 'start_time' => '12:00', 'end_time' => '13:00', 'day_of_week' => null], // All days
            ['name' => 'Cleaning Break', 'start_time' => '15:00', 'end_time' => '16:00', 'day_of_week' => null], // All days
        ];

        foreach ($breaks as $break) {
            ServiceBreak::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'name' => $break['name'],
                    'start_time' => $break['start_time'],
                    'end_time' => $break['end_time'],
                    'day_of_week' => $break['day_of_week'],
                ],
                [
                    'is_recurring' => true,
                ]
            );
        }

        // Holiday: 3rd day from now (full day)
        $holidayDate = Carbon::today()->addDays(3);
        ServiceHoliday::updateOrCreate(
            [
                'service_id' => $service->id,
                'start_datetime' => $holidayDate->copy()->startOfDay(),
            ],
            [
                'name' => 'Public Holiday',
                'end_datetime' => $holidayDate->copy()->endOfDay(),
            ]
        );
    }

    private function configureWomenHaircut(Service $service): void
    {
        // Configuration
        ServiceConfiguration::updateOrCreate(
            ['service_id' => $service->id],
            [
                'duration_minutes' => 60, // appointment duration
                'break_between_minutes' => 10,
                'slot_interval_minutes' => 60, // slots every 1 hour
                'max_concurrent_clients' => 3,
                'booking_advance_days' => 7,
            ]
        );

        // Schedules: Monday to Friday 08:00-20:00, Saturday 10:00-22:00, Sunday off
        $schedules = [
            ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '20:00'], // Monday
            ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '20:00'], // Tuesday
            ['day_of_week' => 3, 'start_time' => '08:00', 'end_time' => '20:00'], // Wednesday
            ['day_of_week' => 4, 'start_time' => '08:00', 'end_time' => '20:00'], // Thursday
            ['day_of_week' => 5, 'start_time' => '08:00', 'end_time' => '20:00'], // Friday
            ['day_of_week' => 6, 'start_time' => '10:00', 'end_time' => '22:00'], // Saturday
            // Sunday (0) - off, no schedule
        ];

        foreach ($schedules as $schedule) {
            ServiceSchedule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => $schedule['day_of_week'],
                ],
                [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'is_available' => true,
                ]
            );
        }

        // Breaks: lunch 12:00-13:00, cleaning 15:00-16:00 (for all days except Sunday)
        $breaks = [
            ['name' => 'Lunch Break', 'start_time' => '12:00', 'end_time' => '13:00', 'day_of_week' => null], // All days
            ['name' => 'Cleaning Break', 'start_time' => '15:00', 'end_time' => '16:00', 'day_of_week' => null], // All days
        ];

        foreach ($breaks as $break) {
            ServiceBreak::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'name' => $break['name'],
                    'start_time' => $break['start_time'],
                    'end_time' => $break['end_time'],
                    'day_of_week' => $break['day_of_week'],
                ],
                [
                    'is_recurring' => true,
                ]
            );
        }

        // Holiday: 3rd day from now (full day)
        $holidayDate = Carbon::today()->addDays(3);
        ServiceHoliday::updateOrCreate(
            [
                'service_id' => $service->id,
                'start_datetime' => $holidayDate->copy()->startOfDay(),
            ],
            [
                'name' => 'Public Holiday',
                'end_datetime' => $holidayDate->copy()->endOfDay(),
            ]
        );
    }
}
