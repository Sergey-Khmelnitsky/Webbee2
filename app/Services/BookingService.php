<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    public function __construct(
        private SlotGeneratorService $slotGenerator
    ) {
    }

    /**
     * Validate and create a booking
     * 
     * @param array $bookingData
     * @return array ['success' => bool, 'appointment' => Appointment|null, 'message' => string]
     */
    public function createBooking(array $bookingData): array
    {
        try {
            DB::beginTransaction();

            // Extract data
            $serviceId = $bookingData['service_id'];
            $date = Carbon::parse($bookingData['date']);
            $startTime = Carbon::parse($bookingData['start_time']);
            $endTime = Carbon::parse($bookingData['end_time']);
            $participants = $bookingData['participants'];

            // Load service with relations
            $service = Service::with(['configuration', 'schedules', 'breaks', 'holidays'])
                ->where('id', $serviceId)
                ->where('is_active', true)
                ->first();

            if (!$service) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'Service not found or inactive',
                ];
            }

            $config = $service->configuration;
            if (!$config) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'Service configuration not found',
                ];
            }

            // Validate date
            if (!$this->isDateValidForBooking($date, $config)) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'Date is outside booking advance days or in the past',
                ];
            }

            // Check if holiday
            if ($this->isHoliday($service, $date)) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'Booking is not available on this date (holiday)',
                ];
            }

            // Validate time slot
            $validationResult = $this->validateTimeSlot($service, $date, $startTime, $endTime, $config);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => $validationResult['message'],
                ];
            }

            // Check if slot is fully booked
            $existingBookingsCount = $this->countExistingBookings($service, $startTime, $endTime);
            if ($existingBookingsCount >= $config->max_concurrent_clients) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'This time slot is fully booked',
                ];
            }

            // Validate duration matches configuration
            $actualDuration = $startTime->diffInMinutes($endTime);
            if ($actualDuration != $config->duration_minutes) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => "Appointment duration must be {$config->duration_minutes} minutes",
                ];
            }

            // Validate participants count (only 1 participant per appointment)
            if (count($participants) !== 1) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => 'Each appointment must have exactly one participant',
                ];
            }

            // Validate participant data
            $participantData = $participants[0];
            $participantValidation = $this->validateParticipant($participantData);
            if (!$participantValidation['valid']) {
                return [
                    'success' => false,
                    'appointment' => null,
                    'message' => $participantValidation['message'],
                ];
            }

            // Create appointment
            $appointment = Appointment::create([
                'service_id' => $service->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending',
                'notes' => null,
            ]);

            // Create participant (use trimmed and validated data)
            AppointmentParticipant::create([
                'appointment_id' => $appointment->id,
                'first_name' => trim($participantData['first_name']),
                'last_name' => trim($participantData['last_name']),
                'email' => trim($participantData['email']),
            ]);

            DB::commit();

            Log::info('Booking created successfully', [
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'appointment' => $appointment->load(['service', 'participants']),
                'message' => 'Appointment booked successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Booking creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'booking_data' => $bookingData,
            ]);

            return [
                'success' => false,
                'appointment' => null,
                'message' => 'An error occurred while creating the booking. Please try again.',
            ];
        }
    }

    /**
     * Check if date is within booking advance days
     */
    private function isDateValidForBooking(Carbon $date, $config): bool
    {
        $today = Carbon::today();
        $maxDate = $today->copy()->addDays($config->booking_advance_days);
        // Allow booking from today up to maxDate (inclusive)
        return $date->gte($today) && !$date->isAfter($maxDate);
    }

    /**
     * Check if date is a holiday
     */
    private function isHoliday(Service $service, Carbon $date): bool
    {
        return $service->holidays()
            ->where('start_datetime', '<=', $date->copy()->endOfDay())
            ->where('end_datetime', '>=', $date->copy()->startOfDay())
            ->exists();
    }

    /**
     * Validate time slot
     */
    private function validateTimeSlot(Service $service, Carbon $date, Carbon $startTime, Carbon $endTime, $config): array
    {
        // Check if date matches
        if (!$startTime->isSameDay($date)) {
            return [
                'valid' => false,
                'message' => 'Start time date does not match selected date',
            ];
        }

        // Get schedule for the day
        $dayOfWeek = $date->dayOfWeek;
        $schedule = $service->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return [
                'valid' => false,
                'message' => 'Service is not available on this day',
            ];
        }

        // Parse schedule times
        $startTimeStr = $this->normalizeTimeString($schedule->start_time);
        $endTimeStr = $this->normalizeTimeString($schedule->end_time);
        
        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $startTimeStr);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $endTimeStr);

        // Check if slot is within working hours
        if ($startTime->lt($workStart) || $endTime->gt($workEnd)) {
            return [
                'valid' => false,
                'message' => 'Time slot is outside working hours',
            ];
        }

        // Check if slot falls within a break
        $breaks = $service->breaks()
            ->where(function ($query) use ($dayOfWeek) {
                $query->where('day_of_week', $dayOfWeek)
                    ->orWhere(function ($q) {
                        $q->whereNull('day_of_week')->where('is_recurring', true);
                    });
            })
            ->get();

        foreach ($breaks as $break) {
            $breakStartTimeStr = $this->normalizeTimeString($break->start_time);
            $breakEndTimeStr = $this->normalizeTimeString($break->end_time);
            
            $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $breakStartTimeStr);
            $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $breakEndTimeStr);

            // Check if appointment overlaps with break
            if ($startTime->lt($breakEnd) && $endTime->gt($breakStart)) {
                return [
                    'valid' => false,
                    'message' => 'Time slot falls within a break period',
                ];
            }
        }

        // Check if slot time aligns with slot intervals
        // Slot should start at valid intervals: workStart + n * (duration + break_between)
        $slotInterval = $config->duration_minutes + $config->break_between_minutes;
        $minutesFromStart = $workStart->diffInMinutes($startTime);
        
        if ($minutesFromStart % $slotInterval !== 0) {
            return [
                'valid' => false,
                'message' => 'Time slot does not align with available booking intervals',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Time slot is valid',
        ];
    }

    /**
     * Count existing bookings for a time slot
     */
    private function countExistingBookings(Service $service, Carbon $startTime, Carbon $endTime): int
    {
        return Appointment::where('service_id', $service->id)
            ->where('start_time', $startTime)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Normalize time string to H:i format
     */
    private function normalizeTimeString(string $time): string
    {
        // If time is in H:i:s format, extract H:i
        if (strlen($time) > 5) {
            return substr($time, 0, 5);
        }
        return $time;
    }

    /**
     * Validate participant data
     * 
     * @param array $participantData
     * @return array ['valid' => bool, 'message' => string]
     */
    private function validateParticipant(array $participantData): array
    {
        // Check required fields
        $requiredFields = ['first_name', 'last_name', 'email'];
        foreach ($requiredFields as $field) {
            if (!isset($participantData[$field]) || empty(trim($participantData[$field]))) {
                return [
                    'valid' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required for participant.',
                ];
            }
        }

        // Validate first name
        $firstName = trim($participantData['first_name']);
        if (strlen($firstName) < 1 || strlen($firstName) > 255) {
            return [
                'valid' => false,
                'message' => 'First name must be between 1 and 255 characters.',
            ];
        }

        // Validate last name
        $lastName = trim($participantData['last_name']);
        if (strlen($lastName) < 1 || strlen($lastName) > 255) {
            return [
                'valid' => false,
                'message' => 'Last name must be between 1 and 255 characters.',
            ];
        }

        // Validate email
        $email = trim($participantData['email']);
        if (strlen($email) > 255) {
            return [
                'valid' => false,
                'message' => 'Email must not exceed 255 characters.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Please provide a valid email address.',
            ];
        }

        // Check for suspicious patterns (basic XSS prevention)
        $suspiciousPatterns = ['<script', 'javascript:', 'onerror=', 'onload='];
        $allFields = $firstName . ' ' . $lastName . ' ' . $email;
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($allFields, $pattern) !== false) {
                return [
                    'valid' => false,
                    'message' => 'Invalid characters detected in participant data.',
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Participant data is valid',
        ];
    }
}
