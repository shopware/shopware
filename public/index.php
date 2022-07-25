<?php declare(strict_types=1);

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\HttpKernel;
use Shopware\Core\Installer\InstallerKernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (\PHP_VERSION_ID < 70403) {
    header('Content-type: text/html; charset=utf-8', true, 503);

    echo '<h2>Error</h2>';
    echo 'Your server is running PHP version ' . \PHP_VERSION . ' but Shopware 6 requires at least PHP 7.4.3';
    exit(1);
}

$classLoader = require __DIR__ . '/../vendor/autoload.php';

if (is_file(dirname(__DIR__) . '/files/update/update.json') || is_dir(dirname(__DIR__) . '/update-assets')) {
    header('Content-type: text/html; charset=utf-8', true, 503);
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 1200');
    if (file_exists(__DIR__ . '/maintenance.html')) {
        readfile(__DIR__ . '/maintenance.html');
    } else {
        readfile(__DIR__ . '/recovery/update/maintenance.html');
    }

    return;
}

$projectRoot = dirname(__DIR__);
if (class_exists(Dotenv::class) && (file_exists($projectRoot . '/.env.local.php') || file_exists($projectRoot . '/.env') || file_exists($projectRoot . '/.env.dist'))) {
    (new Dotenv())->usePutenv()->setProdEnvs(['prod', 'e2e'])->bootEnv(dirname(__DIR__) . '/.env');
}

$appEnv = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? ($appEnv !== 'prod' && $appEnv !== 'e2e'));

if ($debug) {
    umask(0000);

    Debug::enable();
}

$trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false;
if ($trustedProxies) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
}

$trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false;
if ($trustedHosts) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

$request = Request::createFromGlobals();

if (file_exists(dirname(__DIR__) . '/install.lock')) {
    $kernel = new HttpKernel($appEnv, $debug, $classLoader);

    if (($_SERVER['COMPOSER_PLUGIN_LOADER'] ?? $_SERVER['DISABLE_EXTENSIONS'] ?? false) || (!EnvironmentHelper::hasVariable('DATABASE_URL'))) {
        $kernel->setPluginLoader(new \Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader($classLoader));
    }
} else {
    $baseURL = str_replace(basename(__FILE__), '', $_SERVER['SCRIPT_NAME']);
    $baseURL = rtrim($baseURL, '/');
    $installerURL = $baseURL . '/installer';
    if (strpos($_SERVER['REQUEST_URI'], '/installer') === false) {
        header('Location: ' . $installerURL);
        exit;
    }

    $kernel = new InstallerKernel($appEnv, $debug);
}

$result = $kernel->handle($request);

if ($result instanceof Response) {
    $result->send();
    $kernel->terminate($request, $result);
} else {
    $result->getResponse()->send();
    $kernel->terminate($result->getRequest(), $result->getResponse());
}
