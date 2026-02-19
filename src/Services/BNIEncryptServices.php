<?php

namespace OjiePermana\Laravel\Services;

class BNIEncryptServices
{
    private const TIME_DIFF_LIMIT = 480;

    public static function Enc(array $data, string $client_id, string $secret_key): string
    {
        return self::doubleEncrypt(strrev((string) time()) . '.' . (string) json_encode($data), $client_id, $secret_key);
    }

    public static function Dec(string $hashed_string, string $client_id, string $secret_key): ?array
    {
        $parsed = self::doubleDecrypt($hashed_string, $client_id, $secret_key);
        [$timestamp, $data] = array_pad(explode('.', $parsed, 2), 2, null);

        if (abs((int) strrev((string) $timestamp) - time()) <= self::TIME_DIFF_LIMIT) {
            return json_decode((string) $data, true);
        }

        return null;
    }

    private static function doubleEncrypt(string $string, string $cid, string $secret): string
    {
        $result = self::xorEncode($string, $cid);
        $result = self::xorEncode($result, $secret);

        return strtr(rtrim(base64_encode($result), '='), '+/', '-_');
    }

    private static function doubleDecrypt(string $string, string $cid, string $secret): string
    {
        $result = (string) base64_decode(strtr(str_pad($string, (int) ceil(strlen($string) / 4) * 4, '=', STR_PAD_RIGHT), '-_', '+/'));
        $result = self::xorDecode($result, $cid);
        $result = self::xorDecode($result, $secret);

        return $result;
    }

    private static function xorEncode(string $string, string $key): string
    {
        $result = '';
        $strls  = strlen($string);
        $strlk  = strlen($key);

        for ($i = 0; $i < $strls; $i++) {
            $char    = substr($string, $i, 1);
            $keychar = substr($key, ($i % $strlk) - 1, 1);
            $result .= chr((ord($char) + ord($keychar)) % 128);
        }

        return $result;
    }

    private static function xorDecode(string $string, string $key): string
    {
        $result = '';
        $strls  = strlen($string);
        $strlk  = strlen($key);

        for ($i = 0; $i < $strls; $i++) {
            $char    = substr($string, $i, 1);
            $keychar = substr($key, ($i % $strlk) - 1, 1);
            $result .= chr(((ord($char) - ord($keychar)) + 256) % 128);
        }

        return $result;
    }
}
