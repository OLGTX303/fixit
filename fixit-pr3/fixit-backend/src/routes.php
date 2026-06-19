<?php

declare(strict_types=1);

use FixIt\Controllers\AdminController;
use FixIt\Controllers\AuthController;

use FixIt\Controllers\BookingController;
use FixIt\Controllers\CategoryController;
use FixIt\Controllers\CryptoController;
use FixIt\Controllers\KycController;
use FixIt\Controllers\MessageController;
use FixIt\Controllers\ProviderController;
use FixIt\Controllers\ReviewController;
use FixIt\Controllers\StripePaymentController;
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
    $rateLimit = new RateLimitMiddleware();

    $app->group('/api', function (RouteCollectorProxy $group) use (
        $auth, $categories, $providers, $admin, $bookings, $reviews, $messages, $crypto, $kyc, $stripe, $rateLimit
    ) {
        // Stripe webhook — no JWT; verified via Stripe-Signature
        $group->post('/payments/stripe/webhook', [$stripe, 'webhook']);
        $group->get('/auth/captcha', [$auth, 'captchaChallenge'])->add($rateLimit);
        $group->post('/auth/captcha/verify', [$auth, 'captchaVerify'])->add($rateLimit);
        $group->post('/auth/register', [$auth, 'register'])->add($rateLimit);
        $group->post('/auth/login', [$auth, 'login'])->add($rateLimit);
        $group->get('/categories', [$categories, 'list']);
        $group->get('/providers', [$providers, 'list']);
        $group->get('/providers/{id}', [$providers, 'get']);

        $group->group('', function (RouteCollectorProxy $secure) use (
            $providers, $admin, $bookings, $reviews, $messages, $crypto, $kyc, $stripe
        ) {
            $secure->get('/payments/stripe/config', [$stripe, 'config']);
            $secure->post('/payments/stripe/customer', [$stripe, 'ensureCustomer']);
            $secure->post('/payments/stripe/setup-intent', [$stripe, 'createSetupIntent']);
            $secure->post('/payments/stripe/save-payment-method', [$stripe, 'savePaymentMethod']);
            $secure->post('/payments/stripe/pay-with-saved-method', [$stripe, 'payWithSavedMethod']);
            $secure->delete('/payments/stripe/saved-payment-method', [$stripe, 'removeSavedPaymentMethod']);
            $secure->get('/crypto/status', [$crypto, 'status']);
            $secure->get('/crypto/pin/salt', [$crypto, 'getPinSalt']);
            $secure->get('/crypto/public-key', [$crypto, 'myPublicKey']);
            $secure->post('/crypto/pin/setup', [$crypto, 'setupPin']);
            $secure->post('/crypto/pin/verify', [$crypto, 'verifyPin']);
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

            $secure->get('/admin/providers', [$admin, 'allProviders'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/providers/{id}/verify', [$admin, 'verifyProvider'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/users', [$admin, 'listUsers'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/reviews', [$admin, 'listReviews'])
                ->add(new RoleGuard(['admin']));
            $secure->get('/admin/harm-reviews', [$admin, 'listHarmReviews'])
                ->add(new RoleGuard(['admin']));
            $secure->patch('/admin/harm-reviews/{id}', [$admin, 'reviewHarmMessage'])
                ->add(new RoleGuard(['admin']));

            $secure->get('/bookings', [$bookings, 'list']);
            $secure->get('/bookings/{id}', [$bookings, 'get']);
            $secure->post('/bookings', [$bookings, 'create'])
                ->add(new RoleGuard(['customer']));
            $secure->patch('/bookings/{id}/status', [$bookings, 'updateStatus']);
            $secure->delete('/bookings/{id}', [$bookings, 'delete']);

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

    $app->get('/api', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'name' => 'FixIt API',
            'health' => '/api/health',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};