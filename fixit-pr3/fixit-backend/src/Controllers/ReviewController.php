<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\ReviewModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReviewController
{
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['job_id', 'rating']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $jobId = (int) $data['job_id'];
        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            return ResponseHelper::error($response, 'Rating must be between 1 and 5', 422);
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel->findEnriched($jobId);
        if (!$booking) {
            return ResponseHelper::error($response, 'Job not found', 404);
        }
        if ((int) $booking['customer_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Only the customer may review this job', 403);
        }
        if (!in_array($booking['status'], ['completed', 'reviewed'], true)) {
            return ResponseHelper::error($response, 'Job must be completed before reviewing', 422);
        }

        $tipAmount = null;
        if (isset($data['tip_amount']) && $data['tip_amount'] !== '' && $data['tip_amount'] !== null) {
            $tipAmount = max(0.0, (float) $data['tip_amount']);
        }

        $imageUrls = [];
        if (!empty($data['image_urls']) && is_array($data['image_urls'])) {
            foreach (array_slice($data['image_urls'], 0, 9) as $url) {
                $u = filter_var((string) $url, FILTER_VALIDATE_URL);
                if ($u !== false) {
                    $imageUrls[] = $u;
                }
            }
        }

        $review = (new ReviewModel())->create(
            $jobId,
            $rating,
            isset($data['comment']) ? Validator::cleanText((string) $data['comment'], 2000) : null,
            $tipAmount,
            $imageUrls
        );

        if ($booking['status'] === 'completed') {
            $bookingModel->updateStatus($jobId, 'reviewed');
        }

        return ResponseHelper::json($response, $review, 201);
    }

    public function forProvider(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? min(50, max(1, (int) $params['limit'])) : 50;
        $reviews = (new ReviewModel())->forProvider((int) $args['id'], $limit);
        return ResponseHelper::json($response, $reviews);
    }
}