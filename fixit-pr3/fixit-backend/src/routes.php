<?php

declare(strict_types=1);

use FixIt\Controllers\AdminController;
use FixIt\Controllers\AuthController;
use FixIt\Controllers\AvailabilityController;
use FixIt\Controllers\BookingController;
use FixIt\Controllers\CategoryController;
use FixIt\Controllers\CryptoController;
use FixIt\Controllers\CouponController;
use FixIt\Controllers\FavoriteController;
use FixIt\Controllers\HistoryController;
use FixIt\Controllers\KycController;
use FixIt\Controllers\MessageController;
use FixIt\Controllers\ProviderController;
use FixIt\Controllers\ProviderServiceController;
use FixIt\Controllers\ReviewController;
use FixIt\Controllers\StripePaymentController;
use FixIt\Controllers\UserController;
use FixIt\Controllers\WalletController;
use FixIt\Middleware\JwtAuth;
use FixIt\Middleware\RateLimitMiddleware;
use FixIt\Middleware\RoleGuard;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $auth = new AuthController();

    $categories = new CategoryController();
    $providers = new ProviderController();
    $admin = new AdminController();
    $bookings = new BookingController();
    $reviews = new ReviewController();
    $messages = new MessageController();
    $crypto = new CryptoController();
    $kyc = new KycController();
    $stripe = new StripePaymentController();
    $users = new UserController();
    $availability = new AvailabilityController();
    $wallet = new WalletController();
    $providerServices = new ProviderServiceController();
    $favorites = new FavoriteController();
    $coupons = new CouponController();
    $history = new HistoryController();
    $rateLimit = new RateLimitMiddleware();

    $app->group('/api', function (RouteCollectorProxy $group) use (
        $auth, $categories, $providers, $admin, $bookings, $reviews, $messages, $crypto, $kyc, $stripe, $users, $rateLimit, $availability, $wallet, $providerServices, $favorites, $coupons, $history
    ) {
        // Stripe webhook �?no JWT; verified via Stripe-Signature
        $group->post('/payments/stripe/webhook', [$stripe, 'webhook']);
        $group->get('/auth/captcha', [$auth, 'captchaChallenge'])->add($rateLimit);
        $group->post('/auth/captcha/verify', [$auth, 'captchaVerify'])->add($rateLimit);
        $group->post('/auth/register/otp', [$auth, 'registerOtp'])->add($rateLimit);
        $group->post('/auth/register', [$auth, 'register'])->add($rateLimit);
        $group->post('/auth/login', [$auth, 'login'])->add($rateLimit);
        $group->get('/categories', [$categories, 'list']);
        $group->get('/providers', [$providers, 'list']);
        $group->get('/providers/{id}', [$providers, 'get']);
        $group->get('/providers/{id}/availability', [$availability, 'get']);
        $group->get('/providers/{id}/services', [$providerServices, 'list']);
        // Public avatar proxy — streams the image object from R2 by key.
        $group->get('/avatars/{key:.+}', [$users, 'serveAvatar']);
        // Public image proxy — serves review/cover images stored in R2.
        $group->get('/images/{key:.+}', [$users, 'serveImage']);

        $group->group('', function (RouteCollectorProxy $secure) use (
            $providers, $admin, $bookings, $reviews, $messages, $crypto, $kyc, $stripe, $users, $availability, $wallet, $providerServices, $favorites, $coupons, $history, $rateLimit
        ) {
            $secure->post('/providers/{id}/services', [$providerServices, 'create'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->put('/providers/{id}/services/{sid}', [$providerServices, 'update'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->delete('/providers/{id}/services/{sid}', [$providerServices, 'delete'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->get('/me/provider', [$providers, 'me'])
                ->add(new RoleGuard(['provider', 'admin']));

            $secure->get('/wallet', [$wallet, 'get']);
            $secure->post('/wallet/topup', [$wallet, 'topUp'])
                ->add(new RoleGuard(['customer']));
            $secure->post('/wallet/withdraw', [$wallet, 'withdraw'])
                ->add(new RoleGuard(['customer', 'provider']));
            $secure->patch('/users/me', [$users, 'updateMe']);
            $secure->post('/users/me/avatar', [$users, 'uploadAvatar']);
            $secure->post('/users/me/email/otp', [$users, 'requestEmailOtp'])->add($rateLimit);
            $secure->post('/users/me/email/verify', [$users, 'verifyEmailOtp'])->add($rateLimit);
            $secure->get('/payments/stripe/config', [$stripe, 'config']);
            $secure->post('/payments/stripe/customer', [$stripe, 'ensureCustomer']);
            $secure->post('/payments/stripe/setup-intent', [$stripe, 'createSetupIntent']);
            $secure->post('/payments/stripe/save-payment-method', [$stripe, 'savePaymentMethod']);
            $secure->post('/payments/stripe/pay-with-saved-method', [$stripe, 'payWithSavedMethod']);
            $secure->post('/payments/booking/pay', [$stripe, 'payBooking'])
                ->add(new RoleGuard(['customer']));
            $secure->delete('/payments/stripe/saved-payment-method', [$stripe, 'removeSavedPaymentMethod']);
            $secure->get('/crypto/status', [$crypto, 'status']);
            $secure->get('/crypto/pin/salt', [$crypto, 'getPinSalt']);
            $secure->get('/crypto/public-key', [$crypto, 'myPublicKey']);
            $secure->post('/crypto/pin/setup', [$crypto, 'setupPin']);
            $secure->post('/crypto/pin/verify', [$crypto, 'verifyPin'])->add($rateLimit);
            $secure->get('/jobs/{id}/crypto/peers', [$crypto, 'getJobPeers']);
            $secure->get('/jobs/{id}/crypto/key', [$crypto, 'getJobKey']);
            $secure->put('/jobs/{id}/crypto/key', [$crypto, 'saveJobKey']);
            $secure->get('/users/{userId}/crypto/public-key', [$crypto, 'getPublicKey']);

            $secure->post('/providers', [$providers, 'create'])
                ->add(new RoleGuard(['provider']));
            $secure->put('/providers/{id}', [$providers, 'update'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->delete('/providers/{id}', [$providers, 'delete'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->get('/providers/{id}/kyc', [$kyc, 'status'])
                ->add(new RoleGuard(['provider', 'admin']));
            $secure->post('/providers/{id}/kyc/id-recognition', [$kyc, 'submitIdRecognition'])
                ->add(new RoleGuard(['provider']));
            $secure->post('/providers/{id}/kyc/liveness', [$kyc, 'submitLiveness'])
                ->add(new RoleGuard(['provider']));
            $secure->put('/providers/{id}/availability', [$availability, 'save'])
                ->add(new RoleGuard(['provider', 'admin']));

            $secure->get('/admin/providers', [$admin, 'allProviders'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/providers/{id}/verify', [$admin, 'verifyProvider'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/providers/{id}/priority', [$providers, 'setPriority'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/users', [$admin, 'listUsers'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/category-stats', [$admin, 'categoryStats'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/verify-stats', [$admin, 'verifyStats'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/users/{id}/block', [$admin, 'blockUser'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/reviews', [$admin, 'listReviews'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/stripe/stats', [$admin, 'stripeStats'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/harm-reviews', [$admin, 'listHarmReviews'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/harm-reviews/{id}', [$admin, 'reviewHarmMessage'])
                ->add(new RoleGuard(['admin']));

            $secure->post('/providers/{id}/inquiry', [$bookings, 'inquiry'])
                ->add(new RoleGuard(['customer']));

            $secure->get('/favorites', [$favorites, 'list'])
                ->add(new RoleGuard(['customer']));
            $secure->post('/providers/{id}/favorite', [$favorites, 'add'])
                ->add(new RoleGuard(['customer']));
            $secure->delete('/providers/{id}/favorite', [$favorites, 'remove'])
                ->add(new RoleGuard(['customer']));

            $secure->post('/coupons/validate', [$coupons, 'validate'])
                ->add(new RoleGuard(['customer']));
            $secure->get('/coupons/available', [$coupons, 'available'])
                ->add(new RoleGuard(['customer']));

            $secure->get('/me/coupons', [$coupons, 'listMine'])
                ->add(new RoleGuard(['provider']));
            $secure->post('/me/coupons', [$coupons, 'createMine'])
                ->add(new RoleGuard(['provider']));
            $secure->put('/me/coupons/{id}', [$coupons, 'updateMine'])
                ->add(new RoleGuard(['provider']));
            $secure->delete('/me/coupons/{id}', [$coupons, 'deleteMine'])
                ->add(new RoleGuard(['provider']));

            $secure->get('/admin/coupons', [$coupons, 'listAdmin'])
                ->add(new RoleGuard(['admin']));
            $secure->post('/admin/coupons', [$coupons, 'createAdmin'])
                ->add(new RoleGuard(['admin']));
            $secure->put('/admin/coupons/{id}', [$coupons, 'updateAdmin'])
                ->add(new RoleGuard(['admin']));
            $secure->delete('/admin/coupons/{id}', [$coupons, 'deleteAdmin'])
                ->add(new RoleGuard(['admin']));

            $secure->post('/me/history', [$history, 'record'])
                ->add(new RoleGuard(['customer']));
            $secure->get('/me/history', [$history, 'list'])
                ->add(new RoleGuard(['customer']));
            $secure->delete('/me/history', [$history, 'clear'])
                ->add(new RoleGuard(['customer']));

            $secure->get('/bookings', [$bookings, 'list']);
            $secure->get('/bookings/{id}', [$bookings, 'get']);
            $secure->post('/bookings', [$bookings, 'create'])
                ->add(new RoleGuard(['customer']));
            $secure->patch('/bookings/{id}/status', [$bookings, 'updateStatus']);
            $secure->delete('/bookings/{id}', [$bookings, 'delete']);

            $secure->post('/upload/image', [$users, 'uploadImage']);
            $secure->post('/reviews', [$reviews, 'create'])
                ->add(new RoleGuard(['customer']));
            $secure->get('/providers/{id}/reviews', [$reviews, 'forProvider']);

            $secure->get('/jobs/{id}/messages', [$messages, 'list']);
            $secure->post('/jobs/{id}/messages', [$messages, 'create']);
        })->add(new JwtAuth());
    });

    $app->get('/api/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // OTA: the app polls this for the latest signed APK. Reads the newest GitHub
    // release (public repo, no token), cached 5 min to respect rate limits.
    $app->get('/api/app/latest', function ($request, $response) {
        $parseVersionCode = static function (?string $body): ?int {
            if ($body === null || $body === '') {
                return null;
            }
            if (preg_match('/version_code:\s*(\d+)/i', $body, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/<!--\s*version_code:(\d+)\s*-->/', $body, $m)) {
                return (int) $m[1];
            }
            return null;
        };

        $cache = sys_get_temp_dir() . '/fixit_latest_release.json';
        $refresh = !empty($request->getQueryParams()['refresh']);
        if (!$refresh && is_file($cache) && time() - filemtime($cache) < 300) {
            $response->getBody()->write((string) file_get_contents($cache));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $out = ['version' => null, 'version_code' => null, 'apk_url' => null, 'name' => null, 'notes' => null];
        try {
            $ch = curl_init('https://api.github.com/repos/OLGTX303/fixit/releases/latest');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['User-Agent: fixit-ota', 'Accept: application/vnd.github+json'],
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $rel = json_decode((string) $raw, true);
            if ($httpCode === 200 && is_array($rel) && !empty($rel['tag_name'])) {
                $apk = null;
                foreach (($rel['assets'] ?? []) as $a) {
                    if (str_ends_with(strtolower((string) ($a['name'] ?? '')), '.apk')) {
                        $apk = $a['browser_download_url'];
                        break;
                    }
                }
                $body = (string) ($rel['body'] ?? '');
                $out = [
                    'version'      => ltrim((string) $rel['tag_name'], 'v'),
                    'version_code' => $parseVersionCode($body),
                    'apk_url'      => $apk,
                    'name'         => $rel['name'] ?? $rel['tag_name'],
                    'notes'        => $body !== '' ? $body : null,
                ];
                @file_put_contents($cache, json_encode($out));
            }
        } catch (\Throwable) {
            /* return nulls — app treats as "no update" */
        }
        $response->getBody()->write(json_encode($out));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Public client config �?the Google Maps JS key is stored server-side in
    // .env (GOOGLE_MAPS_API_KEY) and fetched at runtime so it never lives in
    // the committed frontend source. Restrict the key in Google Cloud Console.
    $app->get('/api/config/maps', function ($request, $response) {
        $key = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
        $response->getBody()->write(json_encode([
            'maps_api_key' => $key,
            'configured' => $key !== '',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/api', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'name' => 'FixIt API',
            'health' => '/api/health',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};