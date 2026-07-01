<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\BookingModel;
use FixIt\Models\CategoryModel;
use FixIt\Models\CouponModel;
use FixIt\Models\MessageModel;
use FixIt\Models\ProviderModel;
use FixIt\Models\ProviderServiceModel;
use FixIt\Models\StripePaymentModel;
use FixIt\Models\WalletModel;
use FixIt\Services\StripeService;
use FixIt\Support\ResponseHelper;
use FixIt\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookingController
{
    private const PLATFORM_FEE = 5.0;
    private const ESTIMATED_HOURS = 2;

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
        $from = isset($params['from']) && self::isDateParam((string) $params['from']) ? (string) $params['from'] : null;
        $to = isset($params['to']) && self::isDateParam((string) $params['to']) ? (string) $params['to'] : null;
        $bookings = (new BookingModel())->listForUser($user, $limit, $offset, $status, $from, $to);
        return ResponseHelper::json($response, $bookings);
    }

    public function counts(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $from = isset($params['from']) && self::isDateParam((string) $params['from']) ? (string) $params['from'] : null;
        $to = isset($params['to']) && self::isDateParam((string) $params['to']) ? (string) $params['to'] : null;
        return ResponseHelper::json($response, (new BookingModel())->countsForUser($user, $from, $to));
    }

    private static function isDateParam(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $model = new BookingModel();
        $booking = $model->findEnriched((int) $args['id']);
        if (!$booking || !$model->userCanAccess($user, $booking)) {
            return ResponseHelper::error($response, $booking ? 'Forbidden' : 'Booking not found', $booking ? 403 : 404);
        }
        // Payment time for the order-history timeline — comes from StripePayment,
        // not duplicated on Job. Single-booking detail only, so no N+1 on lists.
        $payment = (new StripePaymentModel())->findSucceededByBooking((int) $args['id']);
        $booking['paid_at'] = $payment && !empty($payment['created_at'])
            ? str_replace(' ', 'T', (string) $payment['created_at'])
            : null;
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

        $providerModel = new ProviderModel();
        $provider = $providerModel->findRaw((int) $data['provider_id']);
        if (!$provider || !(bool) $provider['is_verified']) {
            return ResponseHelper::error($response, 'Provider not available', 422);
        }

        $categoryId = (int) $data['category_id'];
        if (!(new CategoryModel())->find($categoryId)) {
            return ResponseHelper::error($response, 'Invalid category_id', 422);
        }
        if (!$providerModel->hasCategory((int) $data['provider_id'], $categoryId)) {
            return ResponseHelper::error($response, 'Provider does not offer this category', 422);
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

        $computedTotal = self::computeBookingTotal($provider, $data);

        $couponId = null;
        $discountAmount = 0.0;
        $finalTotal = $computedTotal;

        if (!empty($data['coupon_code'])) {
            $couponModel = new CouponModel();
            $validation = $couponModel->validate(
                (string) $data['coupon_code'],
                (int) $data['provider_id'],
                $computedTotal,
                (int) $user['id']
            );
            if (!$validation['valid']) {
                return ResponseHelper::error($response, $validation['message'], 422);
            }
            $couponId = (int) $validation['coupon_id'];
            $discountAmount = (float) $validation['discount_amount'];
            $finalTotal = (float) $validation['final_total'];
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel->create([
            'customer_id'          => (int) $user['id'],
            'provider_id'          => (int) $data['provider_id'],
            'category_id'          => $categoryId,
            'scheduled_at'         => $scheduledAt,
            'address'              => Validator::cleanText((string) $data['address'], 255),
            'total'                => $finalTotal,
            'coupon_id'            => $couponId,
            'discount_amount'      => $discountAmount > 0 ? $discountAmount : null,
            'notes'                => isset($data['notes']) ? Validator::cleanText((string) $data['notes'], 2000) : null,
            'status'               => 'requested',
            'recurrence_type'      => $recurrenceType,
            'recurrence_end_date'  => $recurrenceEnd,
        ]);

        if ($couponId !== null) {
            $redeemed = (new CouponModel())->redeem(
                $couponId,
                (int) $user['id'],
                (int) $booking['id'],
                $discountAmount
            );
            if (!$redeemed) {
                $bookingModel->delete((int) $booking['id']);
                return ResponseHelper::error($response, 'Coupon could not be applied — please try again', 409);
            }
        }

        $systemMessage = (new MessageModel())->create(
            (int) $booking['id'],
            (int) $booking['customer_id'],
            [
                'body' => self::bookingSystemMessage($booking),
                'is_encrypted' => false,
                'is_system' => true,
                'harm_status' => 'clear',
                'harm_categories' => [],
            ]
        );
        $booking['latest_message'] = [
            'id' => $systemMessage['id'],
            'job_id' => $systemMessage['job_id'],
            'sender_id' => $systemMessage['sender_id'],
            'body' => $systemMessage['body'],
            'is_encrypted' => $systemMessage['is_encrypted'],
            'is_system' => $systemMessage['is_system'],
            'sent_at' => $systemMessage['sent_at'],
        ];

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
            } else {
                (new WalletModel())->refundBookingPayment((int) $booking['customer_id'], $id);
            }
            $providerUserId = (int) ($booking['provider']['user_id'] ?? 0);
            if ($providerUserId > 0) {
                (new WalletModel())->clawBackJobPayout($providerUserId, $id);
            }
            $updated = $model->updateStatus($id, 'cancelled', $current);
            if (!$updated) {
                return ResponseHelper::error($response, 'Booking status changed concurrently — refresh and retry', 409);
            }
            return ResponseHelper::json($response, $updated);
        }

        if ($user['role'] !== 'admin') {
            $allowed = self::TRANSITIONS[$current] ?? [];
            if (!in_array($newStatus, $allowed, true)) {
                return ResponseHelper::error($response, "Cannot transition from {$current} to {$newStatus}", 422);
            }
        }

        if ($user['role'] === 'customer') {
            return ResponseHelper::error($response, 'Customers cannot change booking status directly', 403);
        }
        if ($user['role'] === 'provider' && $newStatus === 'reviewed') {
            return ResponseHelper::error($response, 'Providers cannot mark jobs as reviewed', 403);
        }

        if ($newStatus === 'completed' && $current !== 'completed') {
            $payment = (new StripePaymentModel())->findSucceededByBooking($id);
            if (!$payment) {
                return ResponseHelper::error($response, 'Cannot complete — booking has not been paid', 422);
            }
            $expectedCents = (int) round(((float) ($booking['total'] ?? 0)) * 100);
            if ($expectedCents > 0 && (int) $payment['amount_cents'] < $expectedCents) {
                return ResponseHelper::error($response, 'Cannot complete — payment amount is insufficient', 422);
            }
        }

        $updated = $model->updateStatus($id, $newStatus, $user['role'] === 'admin' ? null : $current);
        if (!$updated) {
            return ResponseHelper::error($response, 'Booking status changed concurrently — refresh and retry', 409);
        }

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

    /** Server-side price — never trust client-supplied totals. */
    private static function computeBookingTotal(array $provider, array $data): float
    {
        $subtotal = 0.0;
        if (!empty($data['provider_service_id'])) {
            $svc = (new ProviderServiceModel())->find((int) $data['provider_service_id']);
            if (
                $svc
                && (int) $svc['provider_id'] === (int) $provider['id']
                && $svc['is_active']
            ) {
                $subtotal = (float) $svc['price'];
            }
        }
        if ($subtotal <= 0) {
            $rateType = $provider['rate_type'] ?? 'hourly';
            if ($rateType === 'per_job' && isset($provider['per_job_rate'])) {
                $subtotal = (float) $provider['per_job_rate'];
            } else {
                $subtotal = (float) $provider['base_rate'] * self::ESTIMATED_HOURS;
            }
        }
        return round($subtotal + self::PLATFORM_FEE, 2);
    }

    /** @param array<string,mixed> $booking */
    private static function bookingSystemMessage(array $booking): string
    {
        $scheduled = !empty($booking['scheduled_at'])
            ? date('M j, Y g:i A', strtotime(str_replace('T', ' ', (string) $booking['scheduled_at'])))
            : 'Not scheduled';
        $total = $booking['total'] !== null
            ? 'RM ' . number_format((float) $booking['total'], 2)
            : 'Not estimated';

        return implode("\n", [
            'Booking created',
            'Job ID: #' . $booking['id'],
            'Provider: ' . ($booking['provider']['name'] ?? 'Provider'),
            'Customer: ' . ($booking['customer']['name'] ?? 'Customer'),
            'Category: ' . ($booking['category']['name'] ?? 'Service'),
            'Scheduled: ' . $scheduled,
            'Address: ' . ($booking['address'] ?: 'Not provided'),
            'Estimated total: ' . $total,
            'Status: ' . ucfirst(str_replace('_', ' ', (string) $booking['status'])),
        ]);
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
