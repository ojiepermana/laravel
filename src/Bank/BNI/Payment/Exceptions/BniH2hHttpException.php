<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Exceptions;

class BniH2hHttpException extends BniH2hException
{
    /**
     * @param array<string, mixed> $responseBody
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $responseBody = [],
        string $message = 'BNI H2H HTTP request failed'
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}