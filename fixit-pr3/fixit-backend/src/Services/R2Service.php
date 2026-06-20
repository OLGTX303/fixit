<?php

declare(strict_types=1);

namespace FixIt\Services;

/**
 * Minimal Cloudflare R2 (S3-compatible) client using AWS Signature V4 over curl.
 * Avoids pulling in the full aws-sdk-php. Credentials live server-side in .env:
 *   R2_ENDPOINT, R2_BUCKET, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY
 */
final class R2Service
{
    private string $endpoint;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;
    private string $region = 'auto';

    public function __construct()
    {
        $this->endpoint = rtrim((string) ($_ENV['R2_ENDPOINT'] ?? ''), '/');
        $this->bucket = (string) ($_ENV['R2_BUCKET'] ?? '');
        $this->accessKey = (string) ($_ENV['R2_ACCESS_KEY_ID'] ?? '');
        $this->secretKey = (string) ($_ENV['R2_SECRET_ACCESS_KEY'] ?? '');
    }

    public static function isConfigured(): bool
    {
        return ($_ENV['R2_ENDPOINT'] ?? '') !== ''
            && ($_ENV['R2_BUCKET'] ?? '') !== ''
            && ($_ENV['R2_ACCESS_KEY_ID'] ?? '') !== ''
            && ($_ENV['R2_SECRET_ACCESS_KEY'] ?? '') !== '';
    }

    /** Upload an object. $key must not start with a slash. */
    public function putObject(string $key, string $body, string $contentType): void
    {
        $this->signedRequest('PUT', $key, $body, $contentType);
    }

    /**
     * Fetch an object.
     * @return array{body:string,content_type:string}
     */
    public function getObject(string $key): array
    {
        [$status, $headers, $body] = $this->signedRequest('GET', $key, '', null);
        if ($status !== 200) {
            throw new \RuntimeException("R2 GET failed ({$status})");
        }
        return [
            'body' => $body,
            'content_type' => $headers['content-type'] ?? 'application/octet-stream',
        ];
    }

    /**
     * Perform a SigV4-signed request.
     * @return array{0:int,1:array<string,string>,2:string}
     */
    private function signedRequest(string $method, string $key, string $payload, ?string $contentType): array
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('R2 storage is not configured');
        }

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $canonicalUri = '/' . rawurlencode($this->bucket) . '/' . str_replace('%2F', '/', rawurlencode($key));
        $url = 'https://' . $host . $canonicalUri;

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $payloadHash = hash('sha256', $payload);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];
        if ($contentType !== null) {
            $headers['content-type'] = $contentType;
        }
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headers as $h => $v) {
            $canonicalHeaders .= $h . ':' . trim($v) . "\n";
            $signedHeadersList[] = $h;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '',                 // canonical query string (none)
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $algorithm = 'AWS4-HMAC-SHA256';
        $scope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$this->accessKey}/{$scope}, "
            . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $curlHeaders = ["Authorization: {$authorization}"];
        foreach ($headers as $h => $v) {
            $curlHeaders[] = $h . ': ' . $v;
        }

        $ch = curl_init($url);
        $respHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$respHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('R2 request failed: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($method === 'PUT' && $status >= 300) {
            throw new \RuntimeException("R2 PUT failed ({$status}): " . substr((string) $body, 0, 200));
        }

        return [$status, $respHeaders, (string) $body];
    }
}
