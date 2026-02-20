<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Laravel;

use Bank\BNI\Payment\BniH2hClient;
use Bank\BNI\Payment\Contracts\BniH2hClientContract;
use Illuminate\Support\ServiceProvider;

class BniH2hServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../../../config/bni.php', 'bni');

        $this->app->bind(BniH2hClientContract::class, function (): BniH2hClient {
            /** @var array<string, mixed> $config */
            $config = config('bni.payment', []);

            return new BniH2hClient($config);
        });

        $this->app->alias(BniH2hClientContract::class, 'bni-h2h');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../../../../config/bni.php' => config_path('bni.php'),
        ], 'config');
    }
}