<?php

namespace App\Orchid\Screens\Appointments;

use App\Models\Appointment;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AppointmentListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'appointments' => Appointment::with(['service', 'participants'])
                ->latest('start_time')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Appointments';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage appointments and their status.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Create')
                ->icon('bs.plus')
                ->route('platform.systems.appointments.create'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::table('appointments', [
                TD::make('id', 'ID')->sort(),
                TD::make('service.name', 'Service')->sort(),
                TD::make('start_time', 'Start Date & Time')
                    ->render(fn (Appointment $appointment) => $appointment->start_time->format('d.m.Y H:i'))
                    ->sort(),
                TD::make('end_time', 'End Date & Time')
                    ->render(fn (Appointment $appointment) => $appointment->end_time->format('d.m.Y H:i'))
                    ->sort(),
                TD::make('participants', 'Participants')
                    ->render(function (Appointment $appointment) {
                        return $appointment->participants->map(fn ($p) => $p->fullName . ' (' . $p->email . ')')->implode('<br>');
                    }),
                TD::make('status', 'Status')
                    ->render(function (Appointment $appointment) {
                        $color = match ($appointment->status) {
                            'pending'   => 'info',
                            'confirmed' => 'success',
                            'cancelled' => 'danger',
                            'completed' => 'dark',
                            default     => 'light',
                        };
                        return "<span class='badge bg-{$color}'>{$appointment->status}</span>";
                    })
                    ->sort(),
                TD::make('actions', 'Actions')
                    ->render(function (Appointment $appointment) {
                        $buttons = [
                            Link::make('Edit')
                                ->icon('bs.pencil')
                                ->route('platform.systems.appointments.edit', $appointment),
                        ];

                        if ($appointment->status === 'pending') {
                            $buttons[] = Button::make('Confirm')
                                ->icon('bs.check')
                                ->class('btn btn-success')
                                ->method('confirm', ['appointment' => $appointment->id]);
                        }

                        if ($appointment->status === 'pending' || $appointment->status === 'confirmed') {
                            $buttons[] = Button::make('Cancel')
                                ->icon('bs.x')
                                ->class('btn btn-danger')
                                ->method('cancel', ['appointment' => $appointment->id])
                                ->confirm('Are you sure you want to cancel this appointment?');
                        }

                        return implode(' ', $buttons);
                    }),
            ]),
        ];
    }

    /**
     * Confirm appointment
     */
    public function confirm(int $appointment): void
    {
        $appointmentModel = Appointment::findOrFail($appointment);
        $appointmentModel->update(['status' => 'confirmed']);
        Toast::success('Appointment confirmed');
    }

    /**
     * Cancel appointment
     */
    public function cancel(int $appointment): void
    {
        $appointmentModel = Appointment::findOrFail($appointment);
        $appointmentModel->update(['status' => 'cancelled']);
        Toast::info('Appointment cancelled');
    }
}
