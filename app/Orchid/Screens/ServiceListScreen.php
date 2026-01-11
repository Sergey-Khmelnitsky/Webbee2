<?php

namespace App\Orchid\Screens;

use App\Models\Service;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ServiceListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'services' => Service::with('configuration')
                ->orderBy('name')
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
        return 'Services';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Manage services and their availability.';
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
                ->route('platform.systems.services.create'),
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
            Layout::table('services', [
                TD::make('id', 'ID')->sort(),
                TD::make('name', 'Name')->sort()->filter(),
                TD::make('description', 'Description')->render(fn (Service $service) => $service->description ?: '-'),
                TD::make('is_active', 'Status')
                    ->render(function (Service $service) {
                        $color = $service->is_active ? 'success' : 'secondary';
                        $text = $service->is_active ? 'Active' : 'Inactive';
                        return "<span class='badge bg-{$color}'>{$text}</span>";
                    })
                    ->sort(),
                TD::make('configuration', 'Configuration')
                    ->render(function (Service $service) {
                        $config = $service->configuration;
                        if (!$config) {
                            return '<span class="text-muted">Not configured</span>';
                        }
                        return "Duration: {$config->duration_minutes} min";
                    }),
                TD::make('actions', 'Actions')
                    ->render(function (Service $service) {
                        $buttons = [
                            Link::make('Edit')
                                ->icon('bs.pencil')
                                ->route('platform.systems.services.edit', $service),
                        ];

                        if ($service->is_active) {
                            $buttons[] = Button::make('Deactivate')
                                ->icon('bs.x-circle')
                                ->class('btn btn-warning')
                                ->method('toggleActive', ['service' => $service->id])
                                ->confirm('Are you sure you want to deactivate this service?');
                        } else {
                            $buttons[] = Button::make('Activate')
                                ->icon('bs.check-circle')
                                ->class('btn btn-success')
                                ->method('toggleActive', ['service' => $service->id])
                                ->confirm('Are you sure you want to activate this service?');
                        }

                        return implode(' ', $buttons);
                    }),
            ]),
        ];
    }

    /**
     * Toggle service active status
     */
    public function toggleActive(Service $service): void
    {
        $service->update(['is_active' => !$service->is_active]);
        
        $status = $service->is_active ? 'activated' : 'deactivated';
        Toast::info("Service '{$service->name}' has been {$status}.");
    }
}
