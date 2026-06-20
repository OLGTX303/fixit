<?php

declare(strict_types=1);

// Suppress deprecated/notice output — vendor libs (e.g. stripe-php curl_close)
// emit deprecation notices on PHP 8.4+ which corrupt JSON responses.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

use Dotenv\Dotenv;
use FixIt\Middleware\CorsMiddleware;
use FixIt\Middleware\SecurityHeaders;
use FixIt\Support\BootValidator;
use FixIt\Support\ResponseHelper;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

BootValidator::assert();

$app = AppFactory::create();
$app->setBasePath('');

// Preserve raw body for Stripe webhook signature verification
$app->add(function ($request, $handler) {
    $path = $request->getUri()->getPath();
    if (str_ends_with($path, '/payments/stripe/webhook')) {
        $raw = (string) $request->getBody();
        return $handler->handle($request->withAttribute('stripe_raw_body', $raw));
    }
    return $handler->handle($request);
});

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new SecurityHeaders());
$app->add(new CorsMiddleware());

$errorMiddleware = $app->addErrorMiddleware(
    ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    true,
    false
);
$errorMiddleware->setDefaultErrorHandler(function ($request, Throwable $exception, bool $displayErrorDetails) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    if ($displayErrorDetails) {
        error_log($exception->getMessage());
    }
    return ResponseHelper::error($response, 'An unexpected error occurred', 500);
});

(require __DIR__ . '/../src/routes.php')($app);

$app->run();