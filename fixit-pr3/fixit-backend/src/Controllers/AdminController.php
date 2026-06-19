<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\HarmReviewModel;
use FixIt\Models\ProviderModel;
use FixIt\Models\ReviewModel;
use FixIt\Models\UserModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AdminController
{
    public function allProviders(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new ProviderModel())->listEnriched(false, []));
    }

    public function verifyProvider(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();
        if (!array_key_exists('is_verified', $data)) {
            return ResponseHelper::error($response, 'Missing is_verified field', 422);
        }

        $id = (int) $args['id'];
        $model = new ProviderModel();
        if (!$model->findRaw($id)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }

        $provider = $model->setVerification($id, (bool) $data['is_verified']);
        return ResponseHelper::json($response, $provider);
    }

    public function listUsers(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new UserModel())->listAll());
    }

    public function listReviews(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new ReviewModel())->all());
    }

    public function listHarmReviews(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new HarmReviewModel())->listPending());
    }

    public function reviewHarmMessage(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['status']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $review = (new HarmReviewModel())->review(
            (int) $args['id'],
            (int) $user['id'],
            (string) $data['status'],
            isset($data['admin_notes']) ? Validator::cleanText((string) $data['admin_notes'], 2000) : null
        );
        if (!$review) {
            return ResponseHelper::error($response, 'Review not found or invalid status', 404);
        }
        return ResponseHelper::json($response, $review);
    }
}