<?php

declare(strict_types=1);

namespace FixIt\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public static function issue(array $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'dev-secret';
        $now = time();

        return JWT::encode([
            'iss' => 'fixit-api',
            'iat' => $now,
            'exp' => $now + 86400 * 7,
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'],
        ], $secret, 'HS256');
    }

    public static function decode(string $token): object
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'dev-secret';
        return JWT::decode($token, new Key($secret, 'HS256'));
    }
}