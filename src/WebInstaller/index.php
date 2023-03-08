<?php declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/vendor/autoload.php';

$debug = (bool) ($_SERVER['APP_DEBUG'] ?? 0);
$debug = true;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'prod', $debug);

$trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false;
if ($trustedProxies) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST);
}

$request = Request::createFromGlobals();

@set_time_limit(0);
@ignore_user_abort(true);

$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
