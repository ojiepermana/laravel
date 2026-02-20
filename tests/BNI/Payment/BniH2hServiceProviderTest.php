<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\BNI\Payment;

use Bank\BNI\Payment\BniH2hClient;
use Bank\BNI\Payment\Contracts\BniH2hClientContract;
use Bank\BNI\Payment\Laravel\BniH2hServiceProvider;
use Orchestra\Testbench\TestCase;

class BniH2hServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [BniH2hServiceProvider::class];
    }

    // ---------------------------------------------------------------
    // Bindings
    // ---------------------------------------------------------------

    public function test_it_binds_contract_and_alias(): void
    {
        $resolvedByContract = $this->app->make(BniH2hClientContract::class);
        $resolvedByAlias = $this->app->make('bni-h2h');

        $this->assertInstanceOf(BniH2hClient::class, $resolvedByContract);
        $this->assertInstanceOf(BniH2hClient::class, $resolvedByAlias);
    }

    // ---------------------------------------------------------------
    // Config
    // ---------------------------------------------------------------

    public function test_it_merges_default_configuration(): void
    {
        $this->assertSame(30, config('bni.payment.timeout_seconds'));
        $this->assertTrue((bool) config('bni.payment.verify_ssl'));
    }
}
