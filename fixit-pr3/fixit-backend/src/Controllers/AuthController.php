<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use Firebase\JWT\JWT;
use FixIt\Models\EmailOtpModel;
use FixIt\Models\UserModel;
use FixIt\Services\MailService;
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
        // Captcha is enforced at the OTP-send step (registerOtp); registration
        // itself is gated by the email OTP, so no captcha token is needed here.
        $err = Validator::requireFields($data, [
            'name', 'email', 'password', 'role', 'accepted_terms', 'accepted_privacy', 'email_otp',
        ]);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
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

        // Verify the email actually belongs to the registrant via the OTP sent
        // to it (POST /auth/register/otp). Consume on success.
        $otps = new EmailOtpModel();
        $otpRow = $otps->findPendingForEmail($email);
        if (!$otpRow) {
            return ResponseHelper::error($response, 'Email not verified. Request a verification code and enter it.', 422);
        }
        if ((int) $otpRow['attempts'] >= 5) {
            $otps->delete((int) $otpRow['id']);
            return ResponseHelper::error($response, 'Too many attempts. Request a new code.', 429);
        }
        if (!password_verify(trim((string) $data['email_otp']), (string) $otpRow['otp_hash'])) {
            $otps->incrementAttempts((int) $otpRow['id']);
            return ResponseHelper::error($response, 'Incorrect verification code', 422);
        }
        $otps->delete((int) $otpRow['id']);

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
        return ResponseHelper::json($response, ['token' => $token, 'user' => UserModel::toPublic($user)], 201);
    }

    /** POST /api/auth/register/otp — body: { email }. Emails a 6-digit code. */
    public function registerOtp(Request $request, Response $response): Response
    {
        if (!MailService::isConfigured()) {
            return ResponseHelper::error($response, 'Email sending is not configured on the server', 503);
        }

        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['email', 'captcha_id', 'captcha_pass_token']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        // Gate the email-send behind human verification so bots can't spam OTPs.
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        if (!(new SliderCaptchaService())->consumePassToken(
            (string) $data['captcha_id'],
            (string) $data['captcha_pass_token'],
            $ip
        )) {
            return ResponseHelper::error($response, 'Human verification required or expired. Complete the slider puzzle again.', 422);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!Validator::email($email)) {
            return ResponseHelper::error($response, 'Invalid email address', 422);
        }
        if ((new UserModel())->findByEmail($email)) {
            return ResponseHelper::error($response, 'Email already registered', 409);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        (new EmailOtpModel())->createForEmail($email, $otp, 600);

        try {
            (new MailService())->send(
                $email,
                'Your FixIt verification code',
                "Your FixIt email verification code is: {$otp}\n\n"
                . "It expires in 10 minutes. If you did not request this, ignore this email."
            );
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 'Failed to send verification email: ' . $e->getMessage(), 502);
        }

        return ResponseHelper::json($response, ['sent' => true, 'email' => $email]);
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
        if (!empty($user['is_blocked'])) {
            return ResponseHelper::error($response, 'This account has been suspended. Contact support.', 403);
        }

        $token = self::issueToken($user);
        return ResponseHelper::json($response, ['token' => $token, 'user' => UserModel::toPublic($user)]);
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