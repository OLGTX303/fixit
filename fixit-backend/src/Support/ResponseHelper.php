<?php

declare(strict_types=1);

namespace FixIt\Support;

use Psr\Http\Message\ResponseInterface as Response;

final class ResponseHelper
{
    public static function json(Response $response, mixed $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload === false ? '{}' : $payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public static function error(Response $response, string $message, int $status = 400): Response
    {
        return self::json($response, ['error' => $message], $status);
    }
}