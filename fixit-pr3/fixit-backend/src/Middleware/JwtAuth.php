<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use FixIt\Models\UserModel;
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

        // Only the decode is guarded — handing off to the route must stay OUTSIDE
        // the catch, or a downstream error (e.g. a DB exception) gets reported as
        // "Invalid or expired token" 401 and the app wrongly logs the user out.
        try {
            $claims = JWT::decode($matches[1], new Key($_ENV['JWT_SECRET'] ?? 'dev-secret', 'HS256'));
        } catch (\Throwable) {
            return ResponseHelper::error(new SlimResponse(), 'Invalid or expired token', 401);
        }

        $userRow = (new UserModel())->findById((int) $claims->sub);
        if (!$userRow || !empty($userRow['is_blocked'])) {
            return ResponseHelper::error(new SlimResponse(), 'This account has been suspended', 403);
        }

        $user = [
            'id' => (int) $claims->sub,
            'role' => (string) $claims->role,
            'email' => (string) $claims->email,
            'name' => (string) $claims->name,
        ];
        return $handler->handle($request->withAttribute('user', $user));
    }
}