<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\HarmReviewModel;
use FixIt\Models\ProviderModel;
use FixIt\Models\ReviewModel;
use FixIt\Models\StripePaymentModel;
use FixIt\Models\UserModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AdminController
{
    public function allProviders(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $filters = [];
        if (isset($p['verified']) && $p['verified'] !== '') $filters['verified'] = (int) $p['verified'];
        if (isset($p['offset'])) $filters['offset'] = (int) $p['offset'];
        $limit = isset($p['limit']) ? min(50, max(1, (int) $p['limit'])) : 25;
        return ResponseHelper::json($response, (new ProviderModel())->listEnriched(false, $filters, $limit));
    }

    /** Pending / approved provider counts for the verify dashboard. */
    public function verifyStats(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new ProviderModel())->verificationCounts());
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
        $p = $request->getQueryParams();
        $q = trim((string) ($p['q'] ?? ''));
        $limit = min(100, max(1, (int) ($p['limit'] ?? 25)));
        $offset = max(0, (int) ($p['offset'] ?? 0));
        $sort = (string) ($p['sort'] ?? 'name');
        $model = new UserModel();
        return ResponseHelper::json($response, [
            'users'  => $model->listPaged($q, $limit, $offset, $sort),
            'total'  => $model->countFiltered($q),
            'counts' => $model->roleCounts(),
        ]);
    }

    /** Verified-provider count per category — for the admin Categories tab. */
    public function categoryStats(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new \FixIt\Models\ProviderModel())->categoryCounts());
    }

    public function blockUser(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();
        $model = new UserModel();
        $model->setBlocked((int) $args['id'], (bool) ($data['blocked'] ?? false));
        return ResponseHelper::json($response, ['ok' => true]);
    }

    public function listReviews(Request $request, Response $response): Response
    {
        $p = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($p['limit'] ?? 25)));
        $offset = max(0, (int) ($p['offset'] ?? 0));
        $model = new ReviewModel();
        return ResponseHelper::json($response, [
            'reviews'    => $model->listPaged($limit, $offset),
            'total'      => $model->countAll(),
            'avg_rating' => $model->avgRating(),
        ]);
    }

    public function listHarmReviews(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new HarmReviewModel())->listPending());
    }

    public function stripeStats(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, (new StripePaymentModel())->listStats());
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