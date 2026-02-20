<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Contracts;

interface BniH2hClientContract
{
    public function getOAuthToken(bool $forceRefresh = false): string;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getInhouseInquiry(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getInterbankInquiry(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function doPayment(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function getPaymentStatus(array $payload): array;

    public function makeCustomerReferenceNumber(): string;
}