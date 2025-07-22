<?php

namespace OjiePermana\Laravel\Directives;

use Illuminate\Support\Facades\Blade;

class CurrencyDirective
{
    public static function register()
    {
        Blade::directive('currency', function ($expression) {
            return "<?php echo format_rupiah($expression); ?>";
        });
    }
}
