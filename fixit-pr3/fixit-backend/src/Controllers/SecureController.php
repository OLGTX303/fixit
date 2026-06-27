<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\SecureSessionModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\SecureChannel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * X25519 handshake (§4a). Client posts its ephemeral public key; server returns
 * its ephemeral public key + per-session salt. Both derive the same master/mac
 * keys; the server stores them (short TTL) keyed by session_id.
 */
final class SecureController
{
    public function handshake(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $clientPub = base64_decode((string) ($data['client_pub'] ?? ''), true);
        if ($clientPub === false || strlen($clientPub) !== 32) {
            return ResponseHelper::error($response, 'Invalid client_pub', 422);
        }

        $serverKp = sodium_crypto_box_keypair();
        $serverPub = sodium_crypto_box_publickey($serverKp);
        $z = sodium_crypto_scalarmult(sodium_crypto_box_secretkey($serverKp), $clientPub);
        $salt = random_bytes(32);
        $keys = SecureChannel::deriveKeys($z, $salt);

        $sessionId = bin2hex(random_bytes(16));
        $model = new SecureSessionModel();
        $model->purgeExpired();
        $model->create($sessionId, (int) $user['id'], $keys['master'], $keys['mac'], $salt, SecureChannel::SESSION_TTL_SECONDS);

        // Zeroize ephemeral material we no longer need.
        sodium_memzero($z);

        return ResponseHelper::json($response, [
            'session_id' => $sessionId,
            'server_pub' => base64_encode($serverPub),
            'salt' => base64_encode($salt),
            'ttl' => SecureChannel::SESSION_TTL_SECONDS,
        ]);
    }
}
