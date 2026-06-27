<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\CryptoModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CryptoController
{
    private BookingModel $bookings;
    private CryptoModel $crypto;

    public function __construct()
    {
        $this->bookings = new BookingModel();
        $this->crypto = new CryptoModel();
    }

    public function status(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return ResponseHelper::json($response, [
            'pin_configured' => $this->crypto->hasPinSetup((int) $user['id']),
        ]);
    }

    public function getPinSalt(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!$this->crypto->hasPinSetup((int) $user['id'])) {
            return ResponseHelper::error($response, 'PIN not configured', 404);
        }
        return ResponseHelper::json($response, ['pin_salt' => $this->crypto->getPinSalt((int) $user['id'])]);
    }

    public function setupPin(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['pin_salt', 'pin_verifier', 'public_key_jwk', 'wrapped_private_key', 'private_key_iv']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $jwk = is_array($data['public_key_jwk']) ? json_encode($data['public_key_jwk']) : (string) $data['public_key_jwk'];
        $this->crypto->setupPin(
            (int) $user['id'],
            strtolower((string) $data['pin_salt']),
            strtolower((string) $data['pin_verifier']),
            $jwk,
            (string) $data['wrapped_private_key'],
            (string) $data['private_key_iv'],
        );
        return ResponseHelper::json($response, ['configured' => true], 201);
    }

    public function verifyPin(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['pin_verifier']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        if (!$this->crypto->verifyPin((int) $user['id'], strtolower((string) $data['pin_verifier']))) {
            return ResponseHelper::error($response, 'Incorrect PIN', 401);
        }

        $bundle = $this->crypto->getUserCrypto((int) $user['id']);
        return ResponseHelper::json($response, [
            'verified' => true,
            'pin_salt' => $bundle['pin_salt'],
            'wrapped_private_key' => $bundle['wrapped_private_key'],
            'private_key_iv' => $bundle['private_key_iv'],
        ]);
    }

    public function myPublicKey(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $jwk = $this->crypto->getPublicKeyJwk((int) $user['id']);
        if (!$jwk) {
            return ResponseHelper::error($response, 'Encryption keys not configured', 404);
        }
        return ResponseHelper::json($response, ['public_key_jwk' => $jwk]);
    }

    public function getPublicKey(Request $request, Response $response, array $args): Response
    {
        $jwk = $this->crypto->getPublicKeyJwk((int) $args['userId']);
        if (!$jwk) {
            return ResponseHelper::error($response, 'User has no encryption keys', 404);
        }
        return ResponseHelper::json($response, ['public_key_jwk' => $jwk]);
    }

    public function getJobKey(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        if (!$this->canAccessJob($user, $jobId)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $key = $this->crypto->getJobKey($jobId, (int) $user['id']);
        return ResponseHelper::json($response, [
            'encrypted_job_key' => $key['encrypted_job_key'] ?? null,
        ]);
    }

    public function saveJobKey(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        $booking = $this->bookings->findEnriched($jobId);
        if (!$booking || !$this->bookings->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['encrypted_job_key']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $this->crypto->saveJobKey($jobId, (int) $user['id'], (string) $data['encrypted_job_key']);

        if (!empty($data['target_user_id']) && !empty($data['encrypted_job_key_for_target'])) {
            $targetId = (int) $data['target_user_id'];
            $customerId = (int) $booking['customer_id'];
            $providerUserId = (int) ($booking['provider']['user_id'] ?? 0);
            if (!in_array($targetId, [$customerId, $providerUserId], true) || $targetId === (int) $user['id']) {
                return ResponseHelper::error($response, 'Invalid target_user_id for this job', 422);
            }
            $existing = $this->crypto->getJobKey($jobId, $targetId);
            if ($existing && !empty($existing['encrypted_job_key'])) {
                return ResponseHelper::error($response, 'Peer encryption key already provisioned', 409);
            }
            $this->crypto->saveJobKeyIfAbsent($jobId, $targetId, (string) $data['encrypted_job_key_for_target']);
        }

        return ResponseHelper::json($response, ['saved' => true]);
    }

    public function getJobPeers(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $jobId = (int) $args['id'];
        $booking = $this->bookings->findEnriched($jobId);
        if (!$booking || !$this->bookings->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $otherId = (int) $user['id'] === (int) $booking['customer_id']
            ? (int) $booking['provider']['user_id']
            : (int) $booking['customer_id'];

        return ResponseHelper::json($response, [
            'other_user_id' => $otherId,
            'customer_id' => (int) $booking['customer_id'],
            'provider_user_id' => (int) $booking['provider']['user_id'],
        ]);
    }

    /** @param array<string,mixed> $user */
    private function canAccessJob(array $user, int $jobId): bool
    {
        $booking = $this->bookings->findEnriched($jobId);
        return $booking && $this->bookings->userCanAccess($user, $booking);
    }
}