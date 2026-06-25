<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\FavoriteModel;
use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FavoriteController
{
    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 20;
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;

        $items = (new FavoriteModel())->listForUser((int) $user['id'], $limit, $offset);
        return ResponseHelper::json($response, $items);
    }

    public function add(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $providerId = (int) $args['id'];
        if (!(new ProviderModel())->findRaw($providerId)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }

        (new FavoriteModel())->add((int) $user['id'], $providerId);
        return ResponseHelper::json($response, ['favorited' => true, 'provider_id' => $providerId], 201);
    }

    public function remove(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $providerId = (int) $args['id'];
        $removed = (new FavoriteModel())->remove((int) $user['id'], $providerId);
        if (!$removed) {
            return ResponseHelper::error($response, 'Not in favourites', 404);
        }
        return ResponseHelper::json($response, ['favorited' => false, 'provider_id' => $providerId]);
    }
}