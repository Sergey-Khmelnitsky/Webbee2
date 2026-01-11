<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemMenu;
use Orchid\Platform\Menu;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(Dashboard $dashboard): void
    {
        $dashboard->menu
            ->add(Menu::MAIN,
                ItemMenu::label('Записи на прием')
                    ->icon('calendar')
                    ->route('platform.systems.appointment-list-screen')
                    ->title('Бронирование')
            );
    }
}
