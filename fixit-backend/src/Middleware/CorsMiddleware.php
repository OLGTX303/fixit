<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $allowed = $_ENV['CORS_ORIGIN'] ?? '';
        $origin = $request->getHeaderLine('Origin');

        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse(204);
            return $this->applyCors($response, $allowed, $origin);
        }

        if ($allowed === '') {
            return ResponseHelper::error(new SlimResponse(), 'Server misconfigured: CORS_ORIGIN is required', 500);
        }

        $response = $handler->handle($request);
        return $this->applyCors($response, $allowed, $origin);
    }

    private function applyCors(Response $response, string $allowed, string $origin): Response
    {
        $allowedOrigins = array_map('trim', explode(',', $allowed));
        if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
            return $response->withStatus(403);
        }
        $match = ($origin !== '' && in_array($origin, $allowedOrigins, true))
            ? $origin
            : $allowedOrigins[0];

        return $response
            ->withHeader('Access-Control-Allow-Origin', $match)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '600');
    }
}