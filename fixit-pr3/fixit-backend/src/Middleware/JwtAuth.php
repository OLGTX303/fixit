<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class JwtAuth implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return ResponseHelper::error(new SlimResponse(), 'Authentication required', 401);
        }

        try {
            $claims = JWT::decode($matches[1], new Key($_ENV['JWT_SECRET'] ?? 'dev-secret', 'HS256'));
            $user = [
                'id' => (int) $claims->sub,
                'role' => (string) $claims->role,
                'email' => (string) $claims->email,
                'name' => (string) $claims->name,
            ];
            return $handler->handle($request->withAttribute('user', $user));
        } catch (\Throwable) {
            return ResponseHelper::error(new SlimResponse(), 'Invalid or expired token', 401);
        }
    }
}