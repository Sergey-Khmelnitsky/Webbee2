<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menHaircut = Service::where('name', 'Men Haircut')->first();
        $womenHaircut = Service::where('name', 'Women Haircut')->first();

        if (!$menHaircut || !$womenHaircut) {
            $this->command->error('Services not found. Please run ServiceSeeder and ServiceConfigurationSeeder first.');
            return;
        }

        $participants = ParticipantSeeder::getParticipants();
        $participantIndex = 0;

        // Generate appointments for the next 7 days
        $today = Carbon::today();
        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->addDays($i);
            
            // Skip Sunday (day_of_week = 0)
            if ($date->dayOfWeek === 0) {
                continue;
            }

            // Skip holiday (3rd day from today)
            if ($i === 3) {
                continue;
            }

            // Generate appointments for Men Haircut
            $participantIndex = $this->generateAppointmentsForService(
                $menHaircut, 
                $date, 
                $participants, 
                $participantIndex
            );

            // Generate appointments for Women Haircut
            $participantIndex = $this->generateAppointmentsForService(
                $womenHaircut, 
                $date, 
                $participants, 
                $participantIndex
            );
        }

        $this->command->info('Created ' . Appointment::count() . ' appointments with ' . AppointmentParticipant::count() . ' participants');
    }

    private function generateAppointmentsForService(
        Service $service, 
        Carbon $date, 
        array $participantsPool, 
        int $participantIndex
    ): int {
        $config = $service->configuration;
        if (!$config) {
            return $participantIndex;
        }

        // Get schedule for the day
        $dayOfWeek = $date->dayOfWeek;
        $schedule = $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return $participantIndex;
        }

        // Parse schedule times
        $startTimeStr = $this->normalizeTimeString($schedule->start_time);
        $endTimeStr = $this->normalizeTimeString($schedule->end_time);
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimeStr);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTimeStr);

        // Get breaks for this day
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhereNull('day_of_week');
            })
            ->where('is_recurring', true)
            ->get();

        // Generate valid time slots
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        $currentTime = $workStart->copy();

        while ($currentTime->copy()->addMinutes($config->duration_minutes)->lte($workEnd)) {
            $slotStart = $currentTime->copy();
            $slotEnd = $currentTime->copy()->addMinutes($config->duration_minutes);

            // Check if slot is valid (not in break, not in holiday)
            if (!$this->isSlotValid($slotStart, $slotEnd, $breaks, $service, $date)) {
                $currentTime->addMinutes($slotInterval);
                continue;
            }

            // Randomly decide if we create an appointment for this slot (25% chance)
            if (rand(1, 100) <= 25) {
                // Each appointment has exactly 1 participant
                $numParticipants = 1;
                
                // Check existing appointments for this slot
                $existingCount = Appointment::where('service_id', $service->id)
                    ->where('start_time', $slotStart)
                    ->whereNull('deleted_at')
                    ->count();

                // Only create if we don't exceed max_concurrent_clients
                if ($existingCount < $config->max_concurrent_clients) {
                    $appointment = Appointment::create([
                        'service_id' => $service->id,
                        'start_time' => $slotStart,
                        'end_time' => $slotEnd,
                        'status' => 'pending',
                        'notes' => null,
                    ]);

                    // Create 1 participant for this appointment
                    $participant = $participantsPool[$participantIndex % count($participantsPool)];
                    AppointmentParticipant::create([
                        'appointment_id' => $appointment->id,
                        'first_name' => $participant['first_name'],
                        'last_name' => $participant['last_name'],
                        'email' => $participant['email'],
                    ]);
                    $participantIndex++;
                }
            }

            $currentTime->addMinutes($slotInterval);
        }

        return $participantIndex;
    }

    private function normalizeTimeString(string $timeStr): string
    {
        if (strlen($timeStr) > 5) {
            return substr($timeStr, 0, 5);
        }
        return $timeStr;
    }

    private function isSlotValid(Carbon $slotStart, Carbon $slotEnd, $breaks, Service $service, Carbon $date): bool
    {
        // Check if date is a holiday
        $isHoliday = $service->holidays()
            ->where('start_datetime', '<=', $date->copy()->endOfDay())
            ->where('end_datetime', '>=', $date->copy()->startOfDay())
            ->exists();

        if ($isHoliday) {
            return false;
        }

        // Check if slot is in a break
        foreach ($breaks as $break) {
            $breakStart = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->start_time));
            $breakEnd = Carbon::parse($slotStart->format('Y-m-d') . ' ' . $this->normalizeTimeString($break->end_time));
            if ($slotStart->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                return false;
            }
        }

        return true;
    }

}
