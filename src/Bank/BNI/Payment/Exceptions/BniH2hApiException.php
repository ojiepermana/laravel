<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Exceptions;

class BniH2hApiException extends BniH2hException
{
    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        private readonly string $responseCode,
        private readonly array $responseData = [],
        string $message = 'BNI H2H API returned non-success responseCode'
    ) {
        parent::__construct($message);
    }

    public function getResponseCode(): string
    {
        return $this->responseCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}