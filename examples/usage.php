<?php

declare(strict_types=1);

use Bank\BNI\Payment\Contracts\BniH2hClientContract;

/** @var BniH2hClientContract $client */
$client = app(BniH2hClientContract::class);

$ref = $client->makeCustomerReferenceNumber();

$client->doPayment([
    'customerReferenceNumber' => $ref,
]);