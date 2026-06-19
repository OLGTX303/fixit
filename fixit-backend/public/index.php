<?php

declare(strict_types=1);

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