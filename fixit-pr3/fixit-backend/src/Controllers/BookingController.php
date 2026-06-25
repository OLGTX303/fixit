<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\ProviderModel;
use FixIt\Models\WalletModel;
use FixIt\Services\StripeService;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookingController
{
    private const TRANSITIONS = [
        'requested' => ['accepted', 'cancelled'],
        'accepted' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => ['reviewed'],
    ];

    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        // Default page size prevents unbounded loads when the client omits limit.
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $status = isset($params['status']) ? (string) $params['status'] : null;
        $bookings = (new BookingModel())->listForUser($user, $limit, $offset, $status);
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

        // Normalize to MySQL DATETIME; reject unparseable input as 422 (not a 500).
        $ts = strtotime(str_replace('T', ' ', (string) $data['scheduled_at']));
        if ($ts === false) {
            return ResponseHelper::error($response, 'Invalid scheduled_at datetime', 422);
        }
        $scheduledAt = date('Y-m-d H:i:s', $ts);

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
            'scheduled_at'         => $scheduledAt,
            'address'              => Validator::cleanText((string) $data['address'], 255),
            'total'                => isset($data['total']) ? (float) $data['total'] : null,
            'notes'                => isset($data['notes']) ? Validator::cleanText((string) $data['notes'], 2000) : null,
            'status'               => 'requested',
            'recurrence_type'      => $recurrenceType,
            'recurrence_end_date'  => $recurrenceEnd,
        ]);

        return ResponseHelper::json($response, $booking, 201);
    }

    /**
     * Get-or-create a pre-order "inquiry" conversation so a customer can message
     * a provider before booking. Reuses the job-scoped chat: the inquiry is a Job
     * with status 'inquiry', excluded from booking/earnings/request lists.
     */
    public function inquiry(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $providerId = (int) $args['id'];
        $providerModel = new ProviderModel();
        if (!$providerModel->findRaw($providerId)) {
            return ResponseHelper::error($response, 'Provider not found', 404);
        }

        $model = new BookingModel();
        $existing = $model->findInquiry((int) $user['id'], $providerId);
        if ($existing) {
            return ResponseHelper::json($response, $existing);
        }

        $enriched = $providerModel->getEnriched($providerId);
        $categoryId = (int) ($enriched['category_ids'][0] ?? 1);

        $booking = $model->create([
            'customer_id'         => (int) $user['id'],
            'provider_id'         => $providerId,
            'category_id'         => $categoryId,
            'scheduled_at'        => date('Y-m-d H:i:s'),
            'address'             => '',
            'total'               => null,
            'notes'               => null,
            'status'              => 'inquiry',
            'recurrence_type'     => 'none',
            'recurrence_end_date' => null,
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
        $allowedStatuses = ['requested', 'accepted', 'in_progress', 'completed', 'reviewed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return ResponseHelper::error($response, 'Invalid status value', 422);
        }
        $current = (string) $booking['status'];

        // Customer (or admin) cancellation — refund Stripe if already paid.
        if ($newStatus === 'cancelled') {
            if ($user['role'] === 'provider') {
                return ResponseHelper::error($response, 'Providers cannot cancel bookings', 403);
            }
            if ($user['role'] === 'customer' && (int) $booking['customer_id'] !== (int) $user['id']) {
                return ResponseHelper::error($response, 'Forbidden', 403);
            }
            if ($user['role'] !== 'admin' && !in_array($current, ['requested', 'accepted'], true)) {
                return ResponseHelper::error($response, "Cannot cancel from status {$current}", 422);
            }
            if (StripeService::isConfigured()) {
                try {
                    (new StripeService())->refundBookingIfPaid($id, (int) $booking['customer_id']);
                } catch (\Throwable $e) {
                    return ResponseHelper::error($response, 'Refund failed: ' . $e->getMessage(), 502);
                }
            }
            return ResponseHelper::json($response, $model->updateStatus($id, 'cancelled'));
        }

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

        // On first completion, credit the provider's real wallet ledger with their
        // payout (total minus 15% platform fee). Idempotent on the job id, so the
        // wallet balance always reflects earned-but-completed jobs.
        if ($newStatus === 'completed' && $current !== 'completed') {
            $providerUserId = (int) ($booking['provider']['user_id'] ?? 0);
            $total = (float) ($booking['total'] ?? 0);
            $payoutCents = (int) round($total * 100 * 0.85);
            if ($providerUserId > 0 && $payoutCents > 0) {
                (new WalletModel())->creditJobPayout($providerUserId, $payoutCents, $id);
            }
        }

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
        } elseif ($user['role'] === 'customer') {
            if ((int) $booking['customer_id'] !== (int) $user['id']) {
                return ResponseHelper::error($response, 'Forbidden', 403);
            }
            if ($booking['status'] !== 'requested') {
                return ResponseHelper::error($response, 'Only pending bookings can be deleted — use cancel instead', 403);
            }
        } elseif ($user['role'] !== 'admin') {
            return ResponseHelper::error($response, 'Forbidden', 403);
        }

        $model->delete($id);
        return ResponseHelper::json($response, ['deleted' => true]);
    }
}