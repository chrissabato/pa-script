<?php

declare(strict_types=1);

use App\Database\Connection;
use App\View\Renderer;
use Slim\Factory\AppFactory;

// When run under `php -S ... public/index.php` (local dev — see
// pa-script-dev.service), let the built-in server serve real static files
// (public/js/*.js, etc.) directly instead of routing them through Slim,
// which has no route for them.
if (PHP_SAPI === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($path !== false && is_file($path)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Game dates/times are local to Willamette (Salem, OR); "today" must be too.
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Los_Angeles');

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware((bool) ($_ENV['APP_DEBUG'] ?? false), true, true);

$view = new Renderer(__DIR__ . '/../templates');
$pdo = Connection::fromEnv();

(require __DIR__ . '/../src/Routes/routes.php')($app, $pdo, $view);

$app->run();
