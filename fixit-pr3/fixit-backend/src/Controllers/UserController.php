<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\EmailOtpModel;
use FixIt\Models\UserModel;
use FixIt\Services\MailService;
use FixIt\Services\R2Service;
use FixIt\Support\MalaysiaRegions;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserController
{
    private const MAX_AVATAR_BYTES = 4 * 1024 * 1024; // 4 MB
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** PATCH /api/users/me — update own name / email / phone. */
    public function updateMe(Request $request, Response $response): Response
    {
        $auth = $request->getAttribute('user');
        $userId = (int) $auth['id'];
        $data = (array) $request->getParsedBody();
        $users = new UserModel();

        $fields = [];

        if (isset($data['name'])) {
            $name = Validator::cleanText((string) $data['name'], 120);
            if ($name === '') {
                return ResponseHelper::error($response, 'Name cannot be empty', 422);
            }
            $fields['name'] = $name;
        }

        // Email is intentionally NOT updatable here — it must be verified via
        // the OTP flow (requestEmailOtp + verifyEmailOtp).

        if (array_key_exists('phone', $data)) {
            $phone = $data['phone'] === null || $data['phone'] === ''
                ? null
                : Validator::cleanText((string) $data['phone'], 32);
            $fields['phone'] = $phone;
        }

        if (isset($data['latitude'], $data['longitude'])) {
            $lat = (float) $data['latitude'];
            $lng = (float) $data['longitude'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return ResponseHelper::error($response, 'Invalid coordinates', 422);
            }
            $region = isset($data['region']) && MalaysiaRegions::isValid((string) $data['region'])
                ? (string) $data['region']
                : MalaysiaRegions::detect($lat, $lng);
            $fields['latitude'] = $lat;
            $fields['longitude'] = $lng;
            $fields['region'] = $region;
            if (isset($data['location_label'])) {
                $fields['location_label'] = Validator::cleanText((string) $data['location_label'], 120);
            } else {
                $fields['location_label'] = MalaysiaRegions::label($region);
            }
        } elseif (isset($data['region']) && MalaysiaRegions::isValid((string) $data['region'])) {
            $region = (string) $data['region'];
            $fields['region'] = $region;
            $fields['location_label'] = MalaysiaRegions::label($region);
            $centre = MalaysiaRegions::all()[$region]['center'];
            $fields['latitude'] = $centre[0];
            $fields['longitude'] = $centre[1];
        }

        if (!$fields) {
            return ResponseHelper::error($response, 'No updatable fields provided', 422);
        }

        $updated = $users->updateProfile($userId, $fields);
        return ResponseHelper::json($response, ['user' => UserModel::toPublic($updated)]);
    }

    /** POST /api/users/me/email/otp — body: { email }. Sends a 6-digit code. */
    public function requestEmailOtp(Request $request, Response $response): Response
    {
        if (!MailService::isConfigured()) {
            return ResponseHelper::error($response, 'Email sending is not configured on the server', 503);
        }

        $auth = $request->getAttribute('user');
        $userId = (int) $auth['id'];
        $data = (array) $request->getParsedBody();

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!Validator::email($email)) {
            return ResponseHelper::error($response, 'Invalid email address', 422);
        }

        $users = new UserModel();
        if ($users->emailTakenByOther($email, $userId)) {
            return ResponseHelper::error($response, 'Email already in use by another account', 409);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        (new EmailOtpModel())->create($userId, $email, $otp, 600);

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

    /** POST /api/users/me/email/verify — body: { email, otp }. Applies the change. */
    public function verifyEmailOtp(Request $request, Response $response): Response
    {
        $auth = $request->getAttribute('user');
        $userId = (int) $auth['id'];
        $data = (array) $request->getParsedBody();

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $otp = trim((string) ($data['otp'] ?? ''));
        if ($email === '' || $otp === '') {
            return ResponseHelper::error($response, 'Email and code are required', 422);
        }

        $otps = new EmailOtpModel();
        $row = $otps->findPending($userId, $email);
        if (!$row) {
            return ResponseHelper::error($response, 'No valid code found. Request a new one.', 422);
        }
        if ((int) $row['attempts'] >= 5) {
            $otps->delete((int) $row['id']);
            return ResponseHelper::error($response, 'Too many attempts. Request a new code.', 429);
        }
        if (!password_verify($otp, (string) $row['otp_hash'])) {
            $otps->incrementAttempts((int) $row['id']);
            return ResponseHelper::error($response, 'Incorrect code', 422);
        }

        $users = new UserModel();
        if ($users->emailTakenByOther($email, $userId)) {
            $otps->delete((int) $row['id']);
            return ResponseHelper::error($response, 'Email already in use by another account', 409);
        }

        $updated = $users->updateProfile($userId, ['email' => $email]);
        $otps->delete((int) $row['id']);

        return ResponseHelper::json($response, ['user' => $updated]);
    }

    /** POST /api/users/me/avatar — body: { image: "data:image/png;base64,..." } */
    public function uploadAvatar(Request $request, Response $response): Response
    {
        if (!R2Service::isConfigured()) {
            return ResponseHelper::error($response, 'Avatar storage is not configured on the server', 503);
        }

        $auth = $request->getAttribute('user');
        $userId = (int) $auth['id'];
        $data = (array) $request->getParsedBody();

        $raw = (string) ($data['image'] ?? '');
        if ($raw === '') {
            return ResponseHelper::error($response, 'No image provided', 422);
        }

        // Parse data URL: data:image/png;base64,XXXX
        if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $raw, $m)) {
            return ResponseHelper::error($response, 'Image must be a base64 data URL', 422);
        }
        $mime = strtolower($m[1]);
        if (!isset(self::MIME_EXT[$mime])) {
            return ResponseHelper::error($response, 'Unsupported image type. Use JPEG, PNG, WEBP or GIF.', 422);
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '') {
            return ResponseHelper::error($response, 'Invalid base64 image data', 422);
        }
        if (strlen($binary) > self::MAX_AVATAR_BYTES) {
            return ResponseHelper::error($response, 'Image too large (max 4 MB)', 422);
        }

        $ext = self::MIME_EXT[$mime];
        $key = 'avatars/u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        try {
            (new R2Service())->putObject($key, $binary, $mime);
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 'Failed to store avatar: ' . $e->getMessage(), 502);
        }

        $base = rtrim((string) ($_ENV['APP_PUBLIC_URL'] ?? 'https://fixit.olgtx.com'), '/');
        $avatarUrl = $base . '/api/avatars/' . $key;

        $users = new UserModel();
        $updated = $users->updateProfile($userId, ['avatar_url' => $avatarUrl]);

        return ResponseHelper::json($response, ['user' => $updated, 'avatar_url' => $avatarUrl]);
    }

    /** GET /api/avatars/{key} — public proxy that streams the object from R2. */
    public function serveAvatar(Request $request, Response $response, array $args): Response
    {
        $key = (string) ($args['key'] ?? '');
        if ($key === '' || !str_starts_with($key, 'avatars/') || str_contains($key, '..')) {
            return $response->withStatus(404);
        }
        return $this->streamR2($response, $key);
    }

    /** GET /api/images/{key} — public proxy for review/cover images stored in R2. */
    public function serveImage(Request $request, Response $response, array $args): Response
    {
        $key = (string) ($args['key'] ?? '');
        if ($key === '' || !str_starts_with($key, 'images/') || str_contains($key, '..')) {
            return $response->withStatus(404);
        }
        return $this->streamR2($response, $key);
    }

    /** POST /api/upload/image — generic R2 image upload; returns { url }. */
    public function uploadImage(Request $request, Response $response): Response
    {
        if (!R2Service::isConfigured()) {
            return ResponseHelper::error($response, 'Image storage is not configured on the server', 503);
        }

        $auth = $request->getAttribute('user');
        $userId = (int) $auth['id'];
        $data = (array) $request->getParsedBody();

        $raw = (string) ($data['image'] ?? '');
        if ($raw === '') {
            return ResponseHelper::error($response, 'No image provided', 422);
        }

        if (!preg_match('#^data:([^;]+);base64,(.+)$#s', $raw, $m)) {
            return ResponseHelper::error($response, 'Image must be a base64 data URL', 422);
        }
        $mime = strtolower($m[1]);
        if (!isset(self::MIME_EXT[$mime])) {
            return ResponseHelper::error($response, 'Unsupported image type. Use JPEG, PNG, WEBP or GIF.', 422);
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || $binary === '') {
            return ResponseHelper::error($response, 'Invalid base64 image data', 422);
        }
        if (strlen($binary) > self::MAX_AVATAR_BYTES) {
            return ResponseHelper::error($response, 'Image too large (max 4 MB)', 422);
        }

        $ext = self::MIME_EXT[$mime];
        $key = 'images/u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        try {
            (new R2Service())->putObject($key, $binary, $mime);
        } catch (\Throwable $e) {
            return ResponseHelper::error($response, 'Failed to store image: ' . $e->getMessage(), 502);
        }

        $base = rtrim((string) ($_ENV['APP_PUBLIC_URL'] ?? 'https://fixit.olgtx.com'), '/');
        $url = $base . '/api/images/' . $key;

        return ResponseHelper::json($response, ['url' => $url], 201);
    }

    private function streamR2(Response $response, string $key): Response
    {
        try {
            $obj = (new R2Service())->getObject($key);
        } catch (\Throwable) {
            return $response->withStatus(404);
        }
        $response->getBody()->write($obj['body']);
        return $response
            ->withHeader('Content-Type', $obj['content_type'])
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
