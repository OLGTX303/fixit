<?php

declare(strict_types=1);

namespace FixIt\Services;

/**
 * Server-side face match via the local gateway (POST /match/images).
 * Compares the face on the government ID against a live selfie; the gateway
 * returns a 0–100 similarity score (100 = identical, ~30 = different person).
 * Config in .env: FACE_MATCH_URL, FACE_MATCH_API_KEY, FACE_MATCH_MIN_SCORE.
 */
final class FaceMatchService
{
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function isConfigured(): bool
    {
        return ($_ENV['FACE_MATCH_URL'] ?? '') !== '';
    }

    public function minScore(): int
    {
        return (int) ($_ENV['FACE_MATCH_MIN_SCORE'] ?? 65);
    }

    /**
     * @return array{ok:bool, score:?int, message:string, passed:bool}
     */
    public function match(string $binA, string $mimeA, string $binB, string $mimeB): array
    {
        $url = (string) ($_ENV['FACE_MATCH_URL'] ?? '');
        if ($url === '') {
            return ['ok' => false, 'score' => null, 'message' => 'face match not configured', 'passed' => null];
        }

        $tmpA = tempnam(sys_get_temp_dir(), 'fm_') . '.' . (self::EXT[$mimeA] ?? 'jpg');
        $tmpB = tempnam(sys_get_temp_dir(), 'fm_') . '.' . (self::EXT[$mimeB] ?? 'jpg');
        file_put_contents($tmpA, $binA);
        file_put_contents($tmpB, $binB);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => ['X-API-Key: ' . (string) ($_ENV['FACE_MATCH_API_KEY'] ?? 'test')],
                CURLOPT_POSTFIELDS => [
                    'image'  => new \CURLFile($tmpA, $mimeA, 'image'),
                    'image2' => new \CURLFile($tmpB, $mimeB, 'image2'),
                ],
            ]);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($resp === false) {
                return ['ok' => false, 'score' => null, 'message' => 'gateway unreachable: ' . $err, 'passed' => null];
            }

            $json = json_decode((string) $resp, true);
            $ok = (bool) ($json['status']['ok'] ?? false);
            $score = isset($json['data']['score']) ? (int) $json['data']['score'] : null;
            $message = (string) ($json['status']['message'] ?? 'unknown');

            // KYC requires a definitive same-person result — inconclusive = fail.
            $passed = ($ok && $score !== null) ? ($score >= $this->minScore()) : false;

            return ['ok' => $ok, 'score' => $score, 'message' => $message, 'passed' => $passed];
        } finally {
            @unlink($tmpA);
            @unlink($tmpB);
        }
    }
}
