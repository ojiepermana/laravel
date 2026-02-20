<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Laravel;

use Illuminate\Support\Facades\Facade;

class BniH2hFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bni-h2h';
    }
}