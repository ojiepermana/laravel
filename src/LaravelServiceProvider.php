<?php

namespace OjiePermana\Laravel;

use Illuminate\Support\ServiceProvider;
use OjiePermana\Laravel\Directives\CurrencyDirective;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CurrencyDirective::register();
    }

    public function register(): void
    {
        require_once __DIR__ . '/Helpers/IndonesiaHelper.php';
    }
}
