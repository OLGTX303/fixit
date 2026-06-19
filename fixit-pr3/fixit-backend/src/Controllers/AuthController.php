<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use Firebase\JWT\JWT;
use FixIt\Models\UserModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\SliderCaptchaService;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    private const LEGAL_POLICY_VERSION = '2026-06-19';

    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, [
            'name', 'email', 'password', 'role', 'accepted_terms', 'accepted_privacy',
            'captcha_id', 'captcha_pass_token',
        ]);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $captcha = new SliderCaptchaService();
        if (!$captcha->consumePassToken(
            (string) $data['captcha_id'],
            (string) $data['captcha_pass_token'],
            $ip
        )) {
            return ResponseHelper::error($response, 'Human verification required or expired. Complete the slider puzzle again.', 422);
        }

        if (!filter_var($data['accepted_terms'], FILTER_VALIDATE_BOOLEAN)
            || !filter_var($data['accepted_privacy'], FILTER_VALIDATE_BOOLEAN)) {
            return ResponseHelper::error(
                $response,
                'You must accept the Terms of Service and Privacy Policy to register',
                422
            );
        }

        $policyVersion = (string) ($data['legal_policy_version'] ?? self::LEGAL_POLICY_VERSION);
        if ($policyVersion !== self::LEGAL_POLICY_VERSION) {
            return ResponseHelper::error(
                $response,
                'Legal policy version mismatch. Please refresh and accept the current policies.',
                422
            );
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
            isset($data['phone']) ? Validator::cleanText((string) $data['phone'], 32) : null,
            $policyVersion
        );

        $token = self::issueToken($user);
        return ResponseHelper::json($response, ['token' => $token, 'user' => $user], 201);
    }

    public function captchaChallenge(Request $request, Response $response): Response
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        try {
            return ResponseHelper::json($response, (new SliderCaptchaService())->create($ip));
        } catch (\Throwable) {
            return ResponseHelper::error($response, 'Captcha unavailable', 503);
        }
    }

    public function captchaVerify(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['captcha_id', 'captcha_x', 'drag_ms']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $result = (new SliderCaptchaService())->verify(
            (string) $data['captcha_id'],
            (int) $data['captcha_x'],
            $ip,
            (int) $data['drag_ms']
        );

        if (!$result['verified']) {
            return ResponseHelper::error($response, (string) $result['error'], 422);
        }

        return ResponseHelper::json($response, [
            'verified' => true,
            'captcha_id' => $data['captcha_id'],
            'captcha_pass_token' => $result['pass_token'],
        ]);
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