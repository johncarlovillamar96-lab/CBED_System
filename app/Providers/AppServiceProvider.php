<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['layouts.admin','mating'], function ($view) {
            if ($view->name() === 'layouts.admin' && $view->getFactory()->hasSection('inlineCalfDropNotifications')) return;
            $view->with('calfDropNotifications', \App\Support\OverdueCalfNotifications::groups());
        });
        View::composer('layouts.app', function ($view) {
            $view->with('importCooperatives', Schema::hasTable('cooperatives')
                ? DB::table('cooperatives')->orderBy('cooperative_name')->get()
                : collect());
        });
    }
}
