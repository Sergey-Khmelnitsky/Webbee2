<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Service;
use App\Models\ServiceConfiguration;
use App\Models\ServiceSchedule;
use Carbon\Carbon;
use Tests\TestCase;

class BookingApiTest extends TestCase
{

    protected Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test service
        $this->service = Service::factory()->create([
            'name' => 'Test Service',
            'is_active' => true,
        ]);

        // Create service configuration
        ServiceConfiguration::create([
            'service_id' => $this->service->id,
            'duration_minutes' => 30,
            'break_between_minutes' => 5,
            'max_concurrent_clients' => 3,
            'booking_advance_days' => 7,
        ]);

        // Create schedule for Monday (day 1)
        ServiceSchedule::create([
            'service_id' => $this->service->id,
            'day_of_week' => 1, // Monday
            'start_time' => '08:00',
            'end_time' => '20:00',
            'is_available' => true,
        ]);
    }

    public function test_booking_api_requires_date_parameter(): void
    {
        $response = $this->postJson('/api/bookings', [
            'start_time' => '2026-01-15 10:00:00',
            'end_time' => '2026-01-15 10:30:00',
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_booking_api_requires_start_time(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'end_time' => "{$date} 10:30:00",
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_booking_api_requires_end_time(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => "{$date} 10:00:00",
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_booking_api_requires_bookings_array(): void
    {
        $date = Carbon::today()->addDay()->format('Y-m-d');
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => "{$date} 10:00:00",
            'end_time' => "{$date} 10:30:00",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bookings']);
    }

    public function test_booking_api_validates_participant_data(): void
    {
        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => "{$date} 10:00:00",
            'end_time' => "{$date} 10:30:00",
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => '',
                            'last_name' => 'Doe',
                            'email' => 'invalid-email',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'bookings.0.participants.0.first_name',
                'bookings.0.participants.0.email',
            ]);
    }

    public function test_booking_api_creates_appointment_successfully(): void
    {
        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $startTime = "{$date} 10:00:00";
        $endTime = "{$date} 10:30:00";
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('appointments', [
            'service_id' => $this->service->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
        ]);

        $appointment = Appointment::where('service_id', $this->service->id)
            ->where('start_time', $startTime)
            ->first();

        $this->assertNotNull($appointment);
        $this->assertDatabaseHas('appointment_participants', [
            'appointment_id' => $appointment->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
        ]);
    }

    public function test_booking_api_rejects_booking_for_past_date(): void
    {
        $date = Carbon::yesterday()->format('Y-m-d');
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => "{$date} 10:00:00",
            'end_time' => "{$date} 10:30:00",
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_booking_api_rejects_booking_outside_working_hours(): void
    {
        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => "{$date} 07:00:00",
            'end_time' => "{$date} 07:30:00",
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_booking_api_rejects_booking_when_max_concurrent_clients_reached(): void
    {
        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $startTime = "{$date} 10:00:00";
        $endTime = "{$date} 10:30:00";
        
        // Create 3 appointments (max_concurrent_clients = 3)
        for ($i = 0; $i < 3; $i++) {
            $appointment = Appointment::create([
                'service_id' => $this->service->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'pending',
            ]);

            AppointmentParticipant::create([
                'appointment_id' => $appointment->id,
                'first_name' => "User{$i}",
                'last_name' => "Test",
                'email' => "user{$i}@test.com",
            ]);
        }
        
        // Try to book 4th appointment
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_booking_api_creates_multiple_bookings(): void
    {
        $date = Carbon::today()->next(Carbon::MONDAY)->format('Y-m-d');
        if (Carbon::parse($date)->isToday()) {
            $date = Carbon::parse($date)->addWeek()->format('Y-m-d');
        }
        
        $startTime = "{$date} 10:00:00";
        $endTime = "{$date} 10:30:00";
        
        $response = $this->postJson('/api/bookings', [
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'bookings' => [
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'John',
                            'last_name' => 'Doe',
                            'email' => 'john.doe@test.com',
                        ],
                    ],
                ],
                [
                    'service_id' => $this->service->id,
                    'participants' => [
                        [
                            'first_name' => 'Jane',
                            'last_name' => 'Smith',
                            'email' => 'jane.smith@test.com',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $appointments = Appointment::where('service_id', $this->service->id)
            ->where('start_time', $startTime)
            ->get();

        $this->assertCount(2, $appointments);
    }
}
