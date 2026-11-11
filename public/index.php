<?php

/**
 * Front controller: loads config, prepares a long-lived private session for php-simple-login,
 * then runs the Slim app behind the login.
 */

declare(strict_types=1);

use App\Auth\SimpleLoginMiddleware;
use App\Bootstrap\AppFactory;
use App\Config\AppConfig;
use App\Config\ConfigException;
use App\Logger;
use BenjaminKobjolke\SimpleLogin\Session;
use Monolog\Level;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

const LOG_FILE = 'var/logs/app.log';

try {
    $config = AppConfig::fromFile($root . '/config/app.php');
} catch (ConfigException $e) {
    Logger::create($root . '/' . LOG_FILE, Level::Error)->error('Invalid configuration', ['exception' => $e]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Configuration error. See ' . LOG_FILE . ' for details.';
    exit;
}
$logger = Logger::create($root . '/' . LOG_FILE, $config->logLevel);

define('SIMPLE_LOGIN_PASSWORD', $config->loginPassword);
define('SIMPLE_LOGIN_SESSION_LIFETIME', $config->sessionLifetimeSeconds);
define('SIMPLE_LOGIN_CSS_URL', $config->basePath . '/assets/css/login.css');
define('SIMPLE_LOGIN_SESSION_PATH', $root . '/var/sessions');
define('SIMPLE_LOGIN_SESSION_NAME', 'MLFWI_SESSION');
define('SIMPLE_LOGIN_COOKIE_PATH', $config->basePath === '' ? '/' : $config->basePath);

// Safe cookie defaults, private session folder and sliding cookie renewal come from the library.
// Must run before the CSRF guard is built, which needs an active session.
Session::start();

if (!($_SESSION['csrf'] ?? null) instanceof ArrayIterator) {
    $_SESSION['csrf'] = new ArrayIterator();
}

$app = AppFactory::create($config, $root, $logger, $_SESSION['csrf']);
// Added last, so it runs first: nothing is routed before the user is logged in.
$app->add(new SimpleLoginMiddleware());
$app->run();
