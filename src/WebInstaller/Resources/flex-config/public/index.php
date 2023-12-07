<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Installer\InstallerKernel;
use Symfony\Component\HttpFoundation\Request;

$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

if (!file_exists(__DIR__ . '/../.env')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

return function (array $context) {
    $classLoader = require __DIR__ . '/../vendor/autoload.php';

    if (!file_exists(dirname(__DIR__) . '/install.lock')) {
        $baseURL = str_replace(basename(__FILE__), '', $_SERVER['SCRIPT_NAME']);
        $baseURL = rtrim($baseURL, '/');

        if (!str_contains($_SERVER['REQUEST_URI'], '/installer')) {
            header('Location: ' . $baseURL . '/installer');
            exit;
        }
    }

    $appEnv = $context['APP_ENV'] ?? 'dev';
    $debug = (bool) ($context['APP_DEBUG'] ?? ($appEnv !== 'prod'));

    $trustedProxies = $context['TRUSTED_PROXIES'] ?? false;
    if ($trustedProxies) {
        Request::setTrustedProxies(
            explode(',', $trustedProxies),
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    $trustedHosts = $context['TRUSTED_HOSTS'] ?? false;
    if ($trustedHosts) {
        Request::setTrustedHosts(explode(',', $trustedHosts));
    }

    if (!file_exists(dirname(__DIR__) . '/install.lock')) {
        return new InstallerKernel($appEnv, $debug);
    }

    return KernelFactory::create($appEnv, $debug, $classLoader);
};
