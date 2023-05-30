<?php declare(strict_types=1);

use Shopware\Core\HttpKernel;
use Shopware\Core\Installer\InstallerKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

if (!file_exists(__DIR__ . '/../.env') && !file_exists(__DIR__ . '/../.env.dist') && !file_exists(__DIR__ . '/../.env.local.php')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

$_SERVER['APP_RUNTIME_OPTIONS']['prod_envs'] = ['prod', 'e2e'];

return function (array $context) {
    $classLoader = require __DIR__ . '/../vendor/autoload.php';

    if (!file_exists(dirname(__DIR__) . '/install.lock')) {
        $baseURL = str_replace(basename(__FILE__), '', $_SERVER['SCRIPT_NAME']);
        $baseURL = rtrim($baseURL, '/');

        if (strpos($_SERVER['REQUEST_URI'], '/installer') === false) {
            header('Location: ' . $baseURL . '/installer');
            exit;
        }
    }

    if (is_file(dirname(__DIR__) . '/files/update/update.json') || is_dir(dirname(__DIR__) . '/update-assets')) {
        header('Content-type: text/html; charset=utf-8', true, 503);
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 1200');
        if (file_exists(__DIR__ . '/maintenance.html')) {
            readfile(__DIR__ . '/maintenance.html');
        } else {
            readfile(__DIR__ . '/recovery/update/maintenance.html');
        }

        exit;
    }

    $appEnv = $context['APP_ENV'] ?? 'dev';
    $debug = (bool) ($context['APP_DEBUG'] ?? ($appEnv !== 'prod'));

    $trustedProxies = $context['TRUSTED_PROXIES'] ?? false;
    if ($trustedProxies) {
        Request::setTrustedProxies(
            explode(',', $trustedProxies),
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST
        );
    }

    $trustedHosts = $context['TRUSTED_HOSTS'] ?? false;
    if ($trustedHosts) {
        Request::setTrustedHosts(explode(',', $trustedHosts));
    }

    if (!file_exists(dirname(__DIR__) . '/install.lock')) {
        return new InstallerKernel($appEnv, $debug);
    }

    $shopwareHttpKernel = new HttpKernel($appEnv, $debug, $classLoader);

    return new class($shopwareHttpKernel) implements HttpKernelInterface, TerminableInterface {
        private HttpKernel $httpKernel;

        public function __construct(HttpKernel $httpKernel)
        {
            $this->httpKernel = $httpKernel;
        }

        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return $this->httpKernel->handle($request, $type, $catch)->getResponse();
        }

        public function terminate(Request $request, Response $response): void
        {
            $this->httpKernel->terminate($request, $response);
        }
    };
};
