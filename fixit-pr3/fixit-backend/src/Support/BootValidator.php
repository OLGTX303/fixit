<?php

declare(strict_types=1);

namespace FixIt\Support;

final class BootValidator
{
    public static function assert(): void
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be at least 32 characters');
        }

        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
            if (empty($_ENV[$key])) {
                throw new \RuntimeException("Missing required environment variable: {$key}");
            }
        }

        if (empty($_ENV['CORS_ORIGIN'])) {
            throw new \RuntimeException('CORS_ORIGIN must be set to your frontend URL(s)');
        }
    }
}