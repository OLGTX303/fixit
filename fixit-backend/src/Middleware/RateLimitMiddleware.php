<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private const WINDOW_SECONDS = 60;
    private const MAX_ATTEMPTS = 10;

    public function process(Request $request, Handler $handler): Response
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $key = 'auth_' . $ip;
        $now = time();
        $bucket = $this->readBucket($key);

        $bucket = array_values(array_filter($bucket, fn ($ts) => ($now - $ts) < self::WINDOW_SECONDS));
        if (count($bucket) >= self::MAX_ATTEMPTS) {
            return ResponseHelper::error(new SlimResponse(), 'Too many attempts. Try again later.', 429);
        }

        $bucket[] = $now;
        $this->writeBucket($key, $bucket);

        return $handler->handle($request);
    }

    /** @return list<int> */
    private function readBucket(string $key): array
    {
        $path = sys_get_temp_dir() . '/fixit_rl_' . hash('sha256', $key) . '.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    /** @param list<int> $bucket */
    private function writeBucket(string $key, array $bucket): void
    {
        $path = sys_get_temp_dir() . '/fixit_rl_' . hash('sha256', $key) . '.json';
        file_put_contents($path, json_encode($bucket), LOCK_EX);
    }
}