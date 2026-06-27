<?php

declare(strict_types=1);

namespace FixIt\Support;

use FixIt\Services\R2Service;

/** Persist KYC ID photos for server-side face match at liveness. */
final class KycImageStore
{
    private const MAX_BYTES = 6 * 1024 * 1024;

    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function save(int $providerId, string $bin, string $mime): string
    {
        if (strlen($bin) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('KYC image too large (max 6 MB)');
        }
        if (!isset(self::MIME_EXT[$mime])) {
            throw new \InvalidArgumentException('Unsupported KYC image type');
        }

        $ext = self::MIME_EXT[$mime];
        $suffix = bin2hex(random_bytes(8));
        $key = 'kyc/p' . $providerId . '_' . $suffix . '.' . $ext;

        if (R2Service::isConfigured()) {
            (new R2Service())->putObject($key, $bin, $mime);
            $base = rtrim((string) ($_ENV['APP_PUBLIC_URL'] ?? 'https://fixit.olgtx.com'), '/');

            return $base . '/api/kyc/' . $key;
        }

        $dir = self::localDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('KYC storage is not available');
        }
        $name = 'p' . $providerId . '_' . $suffix . '.' . $ext;
        if (file_put_contents($dir . '/' . $name, $bin) === false) {
            throw new \RuntimeException('Failed to write KYC image');
        }

        return '/uploads/kyc/' . $name;
    }

    /** @return array{bin:string,mime:string}|null */
    public static function load(?string $docUrl): ?array
    {
        if ($docUrl === null || $docUrl === '') {
            return null;
        }

        if (preg_match('#/api/kyc/(.+)$#', $docUrl, $m)) {
            if (!R2Service::isConfigured()) {
                return null;
            }
            try {
                $obj = (new R2Service())->getObject($m[1]);
            } catch (\Throwable) {
                return null;
            }

            return [
                'bin' => $obj['body'],
                'mime' => strtolower((string) ($obj['content_type'] ?? 'image/jpeg')),
            ];
        }

        if (str_starts_with($docUrl, '/uploads/kyc/')) {
            $path = self::localDir() . '/' . basename($docUrl);
            if (!is_readable($path)) {
                return null;
            }
            $bin = file_get_contents($path);
            if ($bin === false || $bin === '') {
                return null;
            }

            return [
                'bin' => $bin,
                'mime' => strtolower((string) (mime_content_type($path) ?: 'image/jpeg')),
            ];
        }

        return null;
    }

    private static function localDir(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/kyc';
    }
}