<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Medical records hold sensitive student information, so the module
         * splits "who may open the medical module" from "who may read the
         * private details". Administrators hold both today; a future coach or
         * coordinator role can be granted the module without ever being given
         * findings, restrictions, notes or the uploaded documents.
         */
        Gate::define('view-medical-details', fn (User $user) => $user->role === 'Administrator');
    }
}
