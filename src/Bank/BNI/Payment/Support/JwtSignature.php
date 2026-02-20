<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Support;

final class JwtSignature
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerSegment = self::base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadSegment = self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $headerSegment . '.' . $payloadSegment, $secret, true);

        return $headerSegment . '.' . $payloadSegment . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}