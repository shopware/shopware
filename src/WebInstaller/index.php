<?php declare(strict_types=1);

use Shopware\WebInstaller\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/vendor/autoload.php';

/** @var string $rootFile */
$rootFile = $_SERVER['SCRIPT_FILENAME'];
$configEnv = dirname($rootFile) . '/.env.installer';
if (file_exists($configEnv)) {
    $dotenv = new Dotenv();
    $dotenv->usePutenv(false)->load($configEnv);
}

$debug = true;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'prod', $debug);

$trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false;
if ($trustedProxies) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST);
}

$request = Request::createFromGlobals();

@ignore_user_abort(true);

$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
