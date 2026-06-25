<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BrowsingHistoryModel;
use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HistoryController
{
    public function record(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        if ($err = Validator::requireFields($data, ['provider_id'])) {
            return ResponseHelper::error($response, $err, 422);
        }

        $providerId = (int) $data['provider_id'];
        if (!(new ProviderModel())->findRaw($providerId)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }

        (new BrowsingHistoryModel())->upsert((int) $user['id'], $providerId);
        return ResponseHelper::json($response, ['recorded' => true, 'provider_id' => $providerId], 201);
    }

    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $p = $request->getQueryParams();
        $limit = isset($p['limit']) ? (int) $p['limit'] : 20;
        $offset = isset($p['offset']) ? (int) $p['offset'] : 0;
        $items = (new BrowsingHistoryModel())->listForUser((int) $user['id'], $limit, $offset);
        return ResponseHelper::json($response, $items);
    }

    public function clear(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        (new BrowsingHistoryModel())->clear((int) $user['id']);
        return ResponseHelper::json($response, ['cleared' => true]);
    }
}