<?php

declare(strict_types=1);

namespace Bank\BNI\Payment\Support;

final class InputSanitizer
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $result[$key] = self::sanitizeValue($value);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = self::sanitizeValue($item);
            }

            return $sanitized;
        }

        return $value;
    }
}