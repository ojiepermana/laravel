<?php

declare(strict_types=1);

namespace OjiePermana\Laravel\Bank\BNI\Billing;

class BniBillingEncryptor
{
    private const TIME_DIFF_LIMIT = 480;

    /**
     * @param array<string, mixed> $data
     */
    public static function encryptPayload(array $data, string $clientId, string $secretKey): string
    {
        return self::doubleEncrypt(strrev((string) time()) . '.' . (string) json_encode($data), $clientId, $secretKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decryptPayload(string $hashedString, string $clientId, string $secretKey): ?array
    {
        $parsed = self::doubleDecrypt($hashedString, $clientId, $secretKey);
        [$timestamp, $data] = array_pad(explode('.', $parsed, 2), 2, null);

        if (abs((int) strrev((string) $timestamp) - time()) <= self::TIME_DIFF_LIMIT) {
            $decoded = json_decode((string) $data, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private static function doubleEncrypt(string $value, string $clientId, string $secretKey): string
    {
        $result = self::xorEncode($value, $clientId);
        $result = self::xorEncode($result, $secretKey);

        return strtr(rtrim(base64_encode($result), '='), '+/', '-_');
    }

    private static function doubleDecrypt(string $value, string $clientId, string $secretKey): string
    {
        $result = (string) base64_decode(strtr(str_pad($value, (int) ceil(strlen($value) / 4) * 4, '=', STR_PAD_RIGHT), '-_', '+/'));
        $result = self::xorDecode($result, $clientId);
        $result = self::xorDecode($result, $secretKey);

        return $result;
    }

    private static function xorEncode(string $value, string $key): string
    {
        $result = '';
        $valueLength = strlen($value);
        $keyLength = strlen($key);

        for ($index = 0; $index < $valueLength; $index++) {
            $character = substr($value, $index, 1);
            $keyCharacter = substr($key, ($index % $keyLength) - 1, 1);
            $result .= chr((ord($character) + ord($keyCharacter)) % 128);
        }

        return $result;
    }

    private static function xorDecode(string $value, string $key): string
    {
        $result = '';
        $valueLength = strlen($value);
        $keyLength = strlen($key);

        for ($index = 0; $index < $valueLength; $index++) {
            $character = substr($value, $index, 1);
            $keyCharacter = substr($key, ($index % $keyLength) - 1, 1);
            $result .= chr(((ord($character) - ord($keyCharacter)) + 256) % 128);
        }

        return $result;
    }
}
