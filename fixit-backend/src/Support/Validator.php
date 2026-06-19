<?php

declare(strict_types=1);

namespace FixIt\Support;

final class Validator
{
    public static function requireFields(array $data, array $fields): ?string
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                return "Missing required field: {$field}";
            }
        }
        return null;
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Normalize user text before persisting (not HTML escaping). */
    public static function cleanText(string $value, int $maxLen = 5000): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
        if (mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen);
        }
        return $value;
    }

    public static function passwordStrongEnough(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password must include letters and numbers';
        }
        return null;
    }

    public static function positiveInt(mixed $value, string $field): ?int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            return null;
        }
        return (int) $value;
    }
}