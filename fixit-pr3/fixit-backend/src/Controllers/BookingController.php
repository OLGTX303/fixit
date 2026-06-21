<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\ProviderModel;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookingController
{
    private const TRANSITIONS = [
        'requested' => ['accepted'],
        'accepted' => ['in_progress'],
        'in_progress' => ['completed'],
        'completed' => ['reviewed'],
    ];

    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $bookings = (new BookingModel())->listForUser($user);
        return ResponseHelper::json($response, $bookings);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $booking = (new BookingModel())->findEnriched((int) $args['id']);
        if (!$booking) {
            return ResponseHelper::error($response, 'Booking not found', 404);
        }
        if (!(new BookingModel())->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }
        return ResponseHelper::json($response, $booking);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $err = Validator::requireFields($data, ['provider_id', 'category_id', 'scheduled_at', 'address']);
        if ($err) {
            return ResponseHelper::error($response, $err, 422);
        }

        $provider = (new ProviderModel())->findRaw((int) $data['provider_id']);
        if (!$provider || !(bool) $provider['is_verified']) {
            return ResponseHelper::error($response, 'Provider not available', 422);
        }

        $validRecurrence = ['none', 'weekly', 'biweekly', 'monthly'];
        $recurrenceType  = in_array($data['recurrence_type'] ?? 'none', $validRecurrence, true)
            ? ($data['recurrence_type'] ?? 'none')
            : 'none';
        $recurrenceEnd = null;
        if (!empty($data['recurrence_end_date']) && $recurrenceType !== 'none') {
            $recurrenceEnd = date('Y-m-d', strtotime((string) $data['recurrence_end_date'])) ?: null;
        }

        $booking = (new BookingModel())->create([
            'customer_id'          => (int) $user['id'],
            'provider_id'          => (int) $data['provider_id'],
            'category_id'          => (int) $data['category_id'],
            'scheduled_at'         => str_replace('T', ' ', (string) $data['scheduled_at']),
            'address'              => Validator::cleanText((string) $data['address'], 255),
            'total'                => isset($data['total']) ? (float) $data['total'] : null,
            'notes'                => isset($data['notes']) ? Validator::cleanText((string) $data['notes'], 2000) : null,
            'status'               => 'requested',
            'recurrence_type'      => $recurrenceType,
            'recurrence_end_date'  => $recurrenceEnd,
        ]);

        return ResponseHelper::json($response, $booking, 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new BookingModel();
        $booking = $model->findEnriched($id);
        if (!$booking) {
            return ResponseHelper::error($response, 'Booking not found', 404);
        }
        if (!$model->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $data = (array) $request->getParsedBody();
        $newStatus = (string) ($data['status'] ?? '');
        $allowedStatuses = ['requested', 'accepted', 'in_progress', 'completed', 'reviewed'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return ResponseHelper::error($response, 'Invalid status value', 422);
        }
        $current = (string) $booking['status'];
        if ($user['role'] !== 'admin') {
            $allowed = self::TRANSITIONS[$current] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                return ResponseHelper::error($response, "Cannot transition from {$current} to {$newStatus}", 422);
            }
        }

        if ($user['role'] === 'customer' && $newStatus !== 'reviewed') {
            return ResponseHelper::error($response, 'Customers may only mark jobs as reviewed', 403);
        }
        if ($user['role'] === 'provider' && $newStatus === 'reviewed') {
            return ResponseHelper::error($response, 'Providers cannot mark jobs as reviewed', 403);
        }

        $updated = $model->updateStatus($id, $newStatus);
        return ResponseHelper::json($response, $updated);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $model = new BookingModel();
        $booking = $model->findEnriched($id);
        if (!$booking) {
            return ResponseHelper::error($response, 'Booking not found', 404);
        }
        if ($user['role'] === 'provider') {
            $profile = (new ProviderModel())->findByUserId((int) $user['id']);
            if (!$profile || (int) $booking['provider_id'] !== (int) $profile['id'] || $booking['status'] !== 'requested') {
                return ResponseHelper::error($response, 'Forbidden', 403);
            }
        } elseif ($user['role'] !== 'admin' && (int) $booking['customer_id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $model->delete($id);
        return ResponseHelper::json($response, ['deleted' => true]);
    }
}