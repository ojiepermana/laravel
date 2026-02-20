<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Tests\BNI\Payment;

use Bank\BNI\Payment\Support\InputSanitizer;
use Bank\BNI\Payment\Support\JwtSignature;
use Bank\BNI\Payment\Support\ReferenceNumber;
use PHPUnit\Framework\TestCase;

class SupportUtilitiesTest extends TestCase
{
    // ---------------------------------------------------------------
    // JWT
    // ---------------------------------------------------------------

    public function test_jwt_signature_generates_hs256_base64url_token(): void
    {
        $token = JwtSignature::encode([
            'foo' => 'bar',
            'clientId' => 'IDBNITVktQVBQ',
        ], 'my-secret');

        $parts = explode('.', $token);

        $this->assertCount(3, $parts);
        $this->assertStringNotContainsString('=', $parts[0]);
        $this->assertStringNotContainsString('=', $parts[1]);
        $this->assertStringNotContainsString('=', $parts[2]);

        $header = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);

        $this->assertIsArray($header);
        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
    }

    // ---------------------------------------------------------------
    // Sanitizer
    // ---------------------------------------------------------------

    public function test_input_sanitizer_trims_nested_strings(): void
    {
        $payload = [
            'name' => '  Ojie  ',
            'nested' => [
                'desc' => '  Payment  ',
                'keep' => 123,
            ],
        ];

        $sanitized = InputSanitizer::sanitize($payload);

        $this->assertSame('Ojie', $sanitized['name']);
        $this->assertSame('Payment', $sanitized['nested']['desc']);
        $this->assertSame(123, $sanitized['nested']['keep']);
    }

    // ---------------------------------------------------------------
    // Reference Number
    // ---------------------------------------------------------------

    public function test_reference_number_has_expected_prefix_and_shape(): void
    {
        $value = ReferenceNumber::generate('CRN');

        $this->assertStringStartsWith('CRN', $value);
        $this->assertMatchesRegularExpression('/^CRN\d{20}$/', $value);
    }
}
