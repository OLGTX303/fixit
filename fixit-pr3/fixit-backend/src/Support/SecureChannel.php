<?php

declare(strict_types=1);

namespace FixIt\Support;

/**
 * Dynamic per-interaction encryption (v2 skill) — crypto core.
 *
 * Stateless helpers shared by the handshake controller and the request
 * middleware. Pins every byte so JS (WebCrypto) and PHP (libsodium/openssl)
 * interop exactly:
 *   - X25519 ECDHE  → shared secret Z          (PFS: ephemeral keys, §4)
 *   - HKDF-SHA256    → master/mac/per-interaction keys (salted, §4)
 *   - AES-256-GCM    → body confidentiality+integrity, 12-byte IV (§6)
 *   - HMAC-SHA256    → request signature over a length-prefixed canonical
 *                      string (unforgeable field boundaries, §2)
 *
 * Run the self-test:  php src/Support/SecureChannel.php
 */
final class SecureChannel
{
    public const WINDOW_SECONDS = 60;        // §5 timestamp window
    public const SESSION_TTL_SECONDS = 1800; // 30 min (§4: 15–60 min)
    private const HKDF = 'sha256';

    /** info= must be byte-identical on both ends. */
    private const INFO_MASTER = 'fixit/v2/master';
    private const INFO_MAC = 'fixit/v2/mac';

    /** @return array{master:string,mac:string} 32-byte binary keys */
    public static function deriveKeys(string $sharedZ, string $salt): array
    {
        $master = hash_hkdf(self::HKDF, $sharedZ, 32, self::INFO_MASTER, $salt);
        $mac = hash_hkdf(self::HKDF, $master, 32, self::INFO_MAC, $salt);
        return ['master' => $master, 'mac' => $mac];
    }

    /** Unique key per interaction — counter in info guarantees distinctness (§4b). */
    public static function interactionKey(string $master, string $salt, string $dir, int $counter, string $nonce): string
    {
        $info = "fixit/v2/{$dir}/{$counter}/{$nonce}";
        return hash_hkdf(self::HKDF, $master, 32, $info, $salt);
    }

    /**
     * Length-prefixed canonical string (§2): sort pairs by key (byte-wise),
     * encode each as len(k)|k|len(v)|v|. No value can spoof a boundary.
     * @param array<string,string> $pairs
     */
    public static function canonical(array $pairs): string
    {
        uksort($pairs, static fn ($a, $b) => strcmp($a, $b));
        $out = '';
        foreach ($pairs as $k => $v) {
            $k = (string) $k;
            $v = (string) $v;
            $out .= strlen($k) . '|' . $k . '|' . strlen($v) . '|' . $v . '|';
        }
        return $out;
    }

    /** @param array<string,string> $pairs */
    public static function sign(string $macKey, array $pairs): string
    {
        return hash_hmac('sha256', self::canonical($pairs), $macKey);
    }

    /** @param array<string,string> $pairs */
    public static function verify(string $macKey, array $pairs, string $sig): bool
    {
        return hash_equals(self::sign($macKey, $pairs), $sig);
    }

    /** AES-256-GCM. Returns iv(12) || ciphertext || tag(16) — matches WebCrypto. */
    public static function encrypt(string $key, string $plaintext, string $aad): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($ct === false) {
            throw new \RuntimeException('encrypt failed');
        }
        return $iv . $ct . $tag;
    }

    /** Inverse of encrypt(). Throws on tampering / wrong key. */
    public static function decrypt(string $key, string $blob, string $aad): string
    {
        if (strlen($blob) < 28) {
            throw new \RuntimeException('ciphertext too short');
        }
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, -16);
        $ct = substr($blob, 12, -16);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        if ($pt === false) {
            throw new \RuntimeException('decrypt failed (auth tag mismatch)');
        }
        return $pt;
    }

    /** AAD binds metadata to the ciphertext so the tag covers more than the body (§6). */
    public static function aad(string $sessionId, int $counter, string $nonce, string $timestamp, string $dir, string $extra = ''): string
    {
        return "fixit/v2|{$dir}|{$sessionId}|{$counter}|{$nonce}|{$timestamp}|{$extra}";
    }

    // ── Self-test: simulate both client and server roles in one process. ──
    public static function demo(): void
    {
        $assert = static function (bool $ok, string $msg): void {
            if (!$ok) {
                fwrite(STDERR, "FAIL: {$msg}\n");
                exit(1);
            }
            echo "ok: {$msg}\n";
        };

        // Handshake (X25519 ECDHE).
        $clientKp = sodium_crypto_box_keypair();
        $serverKp = sodium_crypto_box_keypair();
        $clientPk = sodium_crypto_box_publickey($clientKp);
        $serverPk = sodium_crypto_box_publickey($serverKp);
        $zServer = sodium_crypto_scalarmult(sodium_crypto_box_secretkey($serverKp), $clientPk);
        $zClient = sodium_crypto_scalarmult(sodium_crypto_box_secretkey($clientKp), $serverPk);
        $assert(hash_equals($zServer, $zClient), 'X25519 shared secret agrees');

        $salt = random_bytes(32);
        $kc = self::deriveKeys($zClient, $salt);
        $ks = self::deriveKeys($zServer, $salt);
        $assert(hash_equals($kc['master'], $ks['master']) && hash_equals($kc['mac'], $ks['mac']), 'HKDF keys agree');

        // One interaction: client encrypts+signs, server verifies+decrypts.
        $sessionId = bin2hex(random_bytes(8));
        $counter = 1;
        $nonce = self::uuid();
        $ts = (string) (int) (microtime(true) * 1000);
        $plaintext = json_encode(['amount_cents' => 9500, 'booking_id' => 2893]);

        $kEnc = self::interactionKey($kc['master'], $salt, 'request', $counter, $nonce);
        $aad = self::aad($sessionId, $counter, $nonce, $ts, 'request', 'POST /api/wallet/topup');
        $blob = self::encrypt($kEnc, $plaintext, $aad);
        $bodyB64 = base64_encode($blob);
        $sig = self::sign($kc['mac'], [
            'session' => $sessionId, 'counter' => (string) $counter, 'nonce' => $nonce,
            'ts' => $ts, 'method' => 'POST', 'path' => '/api/wallet/topup',
            'body_hash' => hash('sha256', $bodyB64),
        ]);

        // Server side.
        $okSig = self::verify($ks['mac'], [
            'session' => $sessionId, 'counter' => (string) $counter, 'nonce' => $nonce,
            'ts' => $ts, 'method' => 'POST', 'path' => '/api/wallet/topup',
            'body_hash' => hash('sha256', $bodyB64),
        ], $sig);
        $assert($okSig, 'HMAC signature verifies');

        $kDec = self::interactionKey($ks['master'], $salt, 'request', $counter, $nonce);
        $aadS = self::aad($sessionId, $counter, $nonce, $ts, 'request', 'POST /api/wallet/topup');
        $recovered = self::decrypt($kDec, base64_decode($bodyB64), $aadS);
        $assert($recovered === $plaintext, 'AES-256-GCM decrypts to original');

        // Tamper detection.
        $bad = $blob;
        $bad[20] = chr(ord($bad[20]) ^ 0x01);
        try {
            self::decrypt($kDec, $bad, $aadS);
            $assert(false, 'tampered ciphertext rejected');
        } catch (\RuntimeException) {
            $assert(true, 'tampered ciphertext rejected');
        }

        // Forgery: changing a signed field breaks the signature.
        $assert(!self::verify($ks['mac'], [
            'session' => $sessionId, 'counter' => '2', 'nonce' => $nonce, 'ts' => $ts,
            'method' => 'POST', 'path' => '/api/wallet/topup', 'body_hash' => hash('sha256', $bodyB64),
        ], $sig), 'altered counter fails signature');

        echo "ALL PASS\n";
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20));
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    SecureChannel::demo();
}
