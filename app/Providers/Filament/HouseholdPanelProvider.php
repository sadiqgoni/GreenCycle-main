<?php

namespace App\Providers\Filament;

use App\Filament\Household\Pages\HouseholdRegistration;
use App\Filament\Widgets\Greencyclewidgets;
use App\Http\Middleware\RoleRedirect;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class HouseholdPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('household')
            ->path('household')
            ->login()
            ->registration(HouseholdRegistration::class)
            ->colors([
                'primary' => Color::Green,
            ])
            ->discoverResources(in: app_path('Filament/Household/Resources'), for: 'App\\Filament\\Household\\Resources')
            ->discoverPages(in: app_path('Filament/Household/Pages'), for: 'App\\Filament\\Household\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Household/Widgets'), for: 'App\\Filament\\Household\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Greencyclewidgets::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([  FilamentApexChartsPlugin::make()])

            ->authMiddleware([
                Authenticate::class,
                RoleRedirect::class

            ])->viteTheme('resources/css/filament/household/theme.css');

    }
}
