<?php declare(strict_types=1);

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Installer\InstallerKernel;

$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

if (!is_file(__DIR__ . '/../.env') && !is_file(__DIR__ . '/../.env.dist') && !is_file(__DIR__ . '/../.env.local.php')) {
    $_SERVER['APP_RUNTIME_OPTIONS']['disable_dotenv'] = true;
}

$_SERVER['APP_RUNTIME_OPTIONS']['prod_envs'] = ['prod', 'e2e'];

return function (array $context) {
    $classLoader = require __DIR__ . '/../vendor/autoload.php';

    if (!is_file(dirname(__DIR__) . '/install.lock')) {
        $baseURL = str_replace(basename(__FILE__), '', $_SERVER['SCRIPT_NAME']);
        $baseURL = rtrim($baseURL, '/');

        if (!str_contains($_SERVER['REQUEST_URI'], '/installer')) {
            header('Location: ' . $baseURL . '/installer');
            exit;
        }
    }

    if (is_file(dirname(__DIR__) . '/files/update/update.json') || is_dir(dirname(__DIR__) . '/update-assets')) {
        header('Content-type: text/html; charset=utf-8', true, 503);
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 1200');
        if (is_file(__DIR__ . '/maintenance.html')) {
            readfile(__DIR__ . '/maintenance.html');
        } else {
            readfile(__DIR__ . '/recovery/update/maintenance.html');
        }

        exit;
    }

    $appEnv = $context['APP_ENV'] ?? 'dev';
    $debug = (bool) ($context['APP_DEBUG'] ?? ($appEnv !== 'prod'));

    if (!is_file(dirname(__DIR__) . '/install.lock')) {
        return new InstallerKernel($appEnv, $debug);
    }

    $pluginLoader = null;

    if (EnvironmentHelper::getVariable('COMPOSER_PLUGIN_LOADER', false)) {
        $pluginLoader = new ComposerPluginLoader($classLoader, null);
    }

    return KernelFactory::create(
        $appEnv,
        $debug,
        $classLoader,
        $pluginLoader
    );
};
