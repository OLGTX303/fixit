<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use Firebase\JWT\JWT;
use FixIt\Models\UserModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['name', 'email', 'password', 'role']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $role = (string) $data['role'];
        if (!in_array($role, ['customer', 'provider'], true)) {
            return ResponseHelper::error($response, 'Invalid role. Only customer or provider can self-register.', 422);
        }

        $email = strtolower(trim((string) $data['email']));
        if (!Validator::email($email)) {
            return ResponseHelper::error($response, 'Invalid email address', 422);
        }

        $pwErr = Validator::passwordStrongEnough((string) $data['password']);
        if ($pwErr) {
            return ResponseHelper::error($response, $pwErr, 422);
        }

        $users = new UserModel();
        if ($users->findByEmail($email)) {
            return ResponseHelper::error($response, 'Email already registered', 409);
        }

        $hash = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        $user = $users->create(
            Validator::cleanText((string) $data['name'], 120),
            $email,
            $hash,
            $role,
            isset($data['phone']) ? Validator::cleanText((string) $data['phone'], 32) : null
        );

        $token = self::issueToken($user);
        return ResponseHelper::json($response, ['token' => $token, 'user' => $user], 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['email', 'password']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $users = new UserModel();
        $user = $users->findByEmail(strtolower(trim((string) $data['email'])));
        if (!$user || !password_verify((string) $data['password'], (string) $user['password_hash'])) {
            return ResponseHelper::error($response, 'Invalid email or password', 401);
        }

        unset($user['password_hash']);
        $token = self::issueToken($user);
        return ResponseHelper::json($response, ['token' => $token, 'user' => $user]);
    }

    private static function issueToken(array $user): string
    {
        $now = time();
        return JWT::encode([
            'iss' => 'fixit-api',
            'iat' => $now,
            'exp' => $now + 86400 * 7,
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'],
        ], $_ENV['JWT_SECRET'] ?? 'dev-secret', 'HS256');
    }
}