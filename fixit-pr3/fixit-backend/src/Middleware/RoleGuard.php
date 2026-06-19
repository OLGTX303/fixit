<?php

declare(strict_types=1);

namespace FixIt\Middleware;

use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class RoleGuard implements MiddlewareInterface
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $user = $request->getAttribute('user');
        if (!is_array($user) || !in_array($user['role'] ?? '', $this->roles, true)) {
            return ResponseHelper::error(new SlimResponse(), 'Forbidden for this role', 403);
        }
        return $handler->handle($request);
    }
}