<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Support;

final class ReferenceNumber
{
    public static function generate(string $prefix = 'REF'): string
    {
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}