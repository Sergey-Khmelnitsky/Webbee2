# Appointment Booking System

A Laravel-based backend API system for managing appointment bookings for a hair salon. This system allows users to book appointments for multiple services (Men Haircut, Women Haircut, Hair Colouring) with flexible scheduling, breaks, holidays, and concurrent client management.

## Features

- **Service Management**: Configure multiple services with different durations, intervals, and capacity
- **Flexible Scheduling**: Set different working hours for each day of the week
- **Break Management**: Configure recurring breaks (lunch, cleaning, etc.) and one-time holidays
- **Slot Generation**: Automatic generation of available time slots based on service configuration
- **Multi-Service Booking**: Book multiple services simultaneously for multiple participants
- **Capacity Management**: Control maximum concurrent clients per service
- **Admin Panel**: Orchid Platform integration for managing services, appointments, and configurations
- **RESTful API**: JSON-based API for calendar data and booking creation
- **Automated Testing**: Comprehensive test suite covering API endpoints and business logic

## Tech Stack

- **Framework**: Laravel 12.x
- **Database**: PostgreSQL
- **ORM**: Eloquent
- **Admin Panel**: Orchid Platform 14.x
- **Testing**: PHPUnit

## Requirements

- PHP 8.2+
- PostgreSQL 12+
- Composer
- Node.js & NPM (for frontend assets)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/Sergey-Khmelnitsky/Webbee2.git
cd webeetest2
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install frontend dependencies:
```bash
npm install
```

4. Copy environment file:
```bash
cp .env.example .env
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Configure database in `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=calendar
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

7. Run migrations:
```bash
php artisan migrate
```

8. Seed the database:
```bash
php artisan db:seed
```

9. Create admin user:
```bash
php artisan orchid:admin
```

10. Build frontend assets:
```bash
npm run build
```

## Running the Application

Start the development server:
```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

- **Frontend**: `http://localhost:8000/`
- **Admin Panel**: `http://localhost:8000/admin`
- **API**: `http://localhost:8000/api/`

## API Endpoints

### GET /api/calendar

Get available time slots for a specific date and services.

**Query Parameters:**
- `date` (required): Date in Y-m-d format (e.g., 2026-01-15)
- `service_ids[]` (optional): Array of service IDs. If multiple, returns common available slots

**Example:**
```bash
GET /api/calendar?date=2026-01-15&service_ids[]=1&service_ids[]=2
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "1,2",
      "name": "Men Haircut & Women Haircut",
      "service_ids": [1, 2],
      "services": [
        {"id": 1, "name": "Men Haircut", "count": 1},
        {"id": 2, "name": "Women Haircut", "count": 1}
      ],
      "configuration": {
        "duration_minutes": 60,
        "break_between_minutes": 5,
        "max_concurrent_clients": 3,
        "booking_advance_days": 7
      },
      "date": {
        "date": "2026-01-15",
        "day_of_week": "Wednesday",
        "slots": [
          {"start_time": "08:00", "end_time": "11:30"},
          {"start_time": "13:00", "end_time": "14:30"}
        ],
        "has_available_slots": true
      }
    }
  ]
}
```

### POST /api/bookings

Create a booking for one or more people for a single time slot.

**Request Body:**
```json
{
  "date": "2026-01-15",
  "start_time": "2026-01-15 10:00:00",
  "end_time": "2026-01-15 10:30:00",
  "bookings": [
    {
      "service_id": 1,
      "participants": [
        {
          "first_name": "John",
          "last_name": "Doe",
          "email": "john.doe@example.com"
        }
      ]
    },
    {
      "service_id": 2,
      "participants": [
        {
          "first_name": "Jane",
          "last_name": "Smith",
          "email": "jane.smith@example.com"
        }
      ]
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "All appointments booked successfully",
  "data": {
    "appointments": [
      {
        "id": 1,
        "service_id": 1,
        "service_name": "Men Haircut",
        "start_time": "2026-01-15T10:00:00+00:00",
        "end_time": "2026-01-15T10:30:00+00:00",
        "status": "pending",
        "participants": [
          {
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@example.com"
          }
        ]
      }
    ]
  }
}
```

## Database Structure

### Services
- `services`: Service definitions (Men Haircut, Women Haircut, Hair Colouring)
- `service_configurations`: Service settings (duration, break between, slot interval, max clients, advance days)
- `service_schedules`: Working hours for each day of the week
- `service_breaks`: Recurring breaks (lunch, cleaning, etc.)
- `service_holidays`: One-time holidays and closures

### Appointments
- `appointments`: Appointment records with service, time, and status
- `appointment_participants`: Participant details (first name, last name, email)

## Testing

Run the test suite:
```bash
php artisan test
```

Run specific test classes:
```bash
php artisan test --filter=CalendarApiTest
php artisan test --filter=BookingApiTest
```

The test suite includes:
- Calendar API validation and slot generation
- Booking API validation and creation
- Edge cases (past dates, invalid slots, capacity limits)
- Multi-service booking scenarios

## Admin Panel

Access the admin panel at `/admin` after creating an admin user.

**Features:**
- **Services**: Manage services, configurations, schedules, breaks, and holidays
- **Appointments**: View, create, edit, and manage appointments
- **Users & Roles**: Manage admin users and permissions

## Service Configuration

### Men Haircut
- Duration: 30 minutes
- Slot Interval: 10 minutes (slots every 10 minutes)
- Break Between: 5 minutes
- Max Concurrent Clients: 3
- Working Hours: Mon-Fri 08:00-20:00, Sat 10:00-22:00, Sun off
- Breaks: Lunch 12:00-13:00, Cleaning 15:00-16:00

### Women Haircut
- Duration: 60 minutes
- Slot Interval: 60 minutes (slots every 1 hour)
- Break Between: 10 minutes
- Max Concurrent Clients: 3
- Working Hours: Mon-Fri 08:00-20:00, Sat 10:00-22:00, Sun off
- Breaks: Lunch 12:00-13:00, Cleaning 15:00-16:00

### Hair Colouring
- Inactive by default (can be activated in admin panel)

## Business Rules

1. **Slot Validation**: Bookings are only allowed for valid time slots that:
   - Fall within working hours
   - Don't overlap with breaks
   - Don't fall on holidays
   - Align with slot intervals (e.g., every 10 minutes for Men Haircut)
   - Have available capacity

2. **Multi-Service Booking**: When booking multiple services:
   - Common available slots are calculated
   - Maximum duration from all services is used
   - Minimum slot interval from all services is used
   - Each service is validated separately

3. **Capacity Management**: Each service has a maximum concurrent clients limit. Bookings are rejected if the limit is reached.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
