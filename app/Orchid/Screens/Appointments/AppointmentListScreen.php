<?php

namespace App\Orchid\Screens\Appointments;

use App\Models\Appointment;
use Orchid\Screen\Actions\Button;
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
            'appointments' => Appointment::with(['service', 'participants', 'createdBy'])
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
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Create')
                ->icon('plus')
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
                TD::make('id', 'ID')
                    ->sort(),

                TD::make('service.name', 'Service')
                    ->sort(),

                TD::make('start_time', 'Start Date & Time')
                    ->render(fn (Appointment $appointment) => $appointment->start_time->format('d.m.Y H:i'))
                    ->sort(),

                TD::make('end_time', 'End Date & Time')
                    ->render(fn (Appointment $appointment) => $appointment->end_time->format('d.m.Y H:i'))
                    ->sort(),

                TD::make('participants', 'Participants')
                    ->render(fn (Appointment $appointment) => $appointment->participants->map(fn ($p) => $p->full_name)->join(', ')),

                TD::make('status', 'Status')
                    ->render(fn (Appointment $appointment) => match($appointment->status) {
                        'pending' => '<span class="badge badge-warning">Pending</span>',
                        'confirmed' => '<span class="badge badge-success">Confirmed</span>',
                        'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
                        'completed' => '<span class="badge badge-info">Completed</span>',
                        default => $appointment->status,
                    })
                    ->sort(),

                TD::make('actions', 'Actions')
                    ->render(function (Appointment $appointment) {
                        $buttons = [];

                        $buttons[] = Button::make('Edit')
                            ->route('platform.systems.appointments.edit', $appointment->id)
                            ->icon('pencil')
                            ->class('btn btn-primary');

                        if ($appointment->status === 'pending') {
                            $buttons[] = Button::make('Confirm')
                                ->method('confirm')
                                ->parameters(['appointment' => $appointment->id])
                                ->icon('check')
                                ->class('btn btn-success');
                        }

                        if (in_array($appointment->status, ['pending', 'confirmed'])) {
                            $buttons[] = Button::make('Cancel')
                                ->method('cancel')
                                ->parameters(['appointment' => $appointment->id])
                                ->icon('close')
                                ->class('btn btn-danger')
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
