<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use FixIt\Models\SecureSessionModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\SecureChannel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response as SlimResponse;

/**
 * Enforces the dynamic per-interaction encryption channel on sensitive writes.
 * Runs AFTER JwtAuth (needs the authenticated user). Verification order is the
 * cheapest-first sequence from the spec (§5): timestamp → nonce → HMAC → decrypt.
 */
final class SecureChannelMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $user = $request->getAttribute('user');
        if (!$user) {
            return $this->fail('Authentication required', 401);
        }

        $sessionId = $request->getHeaderLine('X-Sec-Session');
        $counter   = $request->getHeaderLine('X-Sec-Counter');
        $nonce     = $request->getHeaderLine('X-Sec-Nonce');
        $ts        = $request->getHeaderLine('X-Sec-Ts');
        $sig       = $request->getHeaderLine('X-Sec-Sign');
        if ($sessionId === '' || $counter === '' || $nonce === '' || $ts === '' || $sig === '') {
            return $this->fail('Secure channel required for this endpoint', 426);
        }

        $model = new SecureSessionModel();
        $session = $model->find($sessionId, (int) $user['id']);
        if (!$session) {
            return $this->fail('Secure session not found or expired — re-handshake', 401);
        }

        // 1) Timestamp window — bounds how long a nonce must be remembered (§5).
        if (abs((int) (microtime(true) * 1000) - (int) $ts) > SecureChannel::WINDOW_SECONDS * 1000) {
            return $this->fail('Request timestamp outside allowed window', 401);
        }

        // 2) Atomic replay check.
        if (!$model->claimNonce($sessionId, $nonce, SecureChannel::WINDOW_SECONDS + 30)) {
            return $this->fail('Replay detected', 401);
        }

        // 3) Signature over the length-prefixed canonical string (§2).
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $bodyB64 = (string) $request->getBody();
        $signedPairs = [
            'session' => $sessionId, 'counter' => $counter, 'nonce' => $nonce, 'ts' => $ts,
            'method' => $method, 'path' => $path, 'body_hash' => hash('sha256', $bodyB64),
        ];
        if (!SecureChannel::verify($session['mac_key'], $signedPairs, $sig)) {
            return $this->fail('Signature verification failed', 401);
        }

        // 4) Decrypt the body.
        $extra = $method . ' ' . $path;
        try {
            $blob = base64_decode($bodyB64, true);
            if ($blob === false) {
                throw new \RuntimeException('bad body encoding');
            }
            $kReq = SecureChannel::interactionKey($session['master_secret'], $session['salt'], 'request', (int) $counter, $nonce);
            $aad = SecureChannel::aad($sessionId, (int) $counter, $nonce, $ts, 'request', $extra);
            $plaintext = SecureChannel::decrypt($kReq, $blob, $aad);
        } catch (\Throwable) {
            return $this->fail('Decryption failed', 400);
        }

        $parsed = $plaintext === '' ? [] : json_decode($plaintext, true);
        if (!is_array($parsed)) {
            return $this->fail('Decrypted payload is not valid JSON', 400);
        }

        $response = $handler->handle($request->withParsedBody($parsed));

        // 5) Encrypt the response with a distinct per-interaction key (dir=response).
        $plainResp = (string) $response->getBody();
        $kResp = SecureChannel::interactionKey($session['master_secret'], $session['salt'], 'response', (int) $counter, $nonce);
        $aadResp = SecureChannel::aad($sessionId, (int) $counter, $nonce, $ts, 'response', $extra);
        $encResp = base64_encode(SecureChannel::encrypt($kResp, $plainResp, $aadResp));

        $stream = (new StreamFactory())->createStream($encResp);
        return $response->withBody($stream)
            ->withHeader('X-Sec-Enc', '1')
            ->withHeader('Content-Type', 'application/octet-stream');
    }

    private function fail(string $message, int $code): Response
    {
        return ResponseHelper::error(new SlimResponse(), $message, $code);
    }
}
