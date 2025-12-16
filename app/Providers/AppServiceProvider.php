<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use Livewire\Livewire;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            LoginResponseContract::class,
            LoginResponse::class
        );

        $this->app->singleton(
            LogoutResponseContract::class,
            LogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Establecer la zona horaria para toda la aplicación
        date_default_timezone_set('America/Lima');
        \Carbon\Carbon::setTestNow();

        // Ya no necesitamos registrar el widget personalizado

        // Definir el gate para permisos de administrador
        Gate::define('admin', function (User $user) {
            // Aquí definimos quién es administrador
            // Por ejemplo, usuarios con el rol admin o el ID 1 (primer usuario)
            return $user->hasRole('admin') || $user->id === 1;
        });

        // Registrar observadores
        \App\Models\ProductConfiguration::observe(\App\Observers\ProductConfigurationObserver::class);
        \App\Models\QuotationDetail::observe(\App\Observers\QuotationDetailObserver::class);
    }
}
