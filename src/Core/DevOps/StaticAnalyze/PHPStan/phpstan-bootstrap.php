<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Symfony\Component\Dotenv\Dotenv;

if (!\defined('TEST_PROJECT_DIR')) {
    \define('TEST_PROJECT_DIR', (function (): string {
        if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
            return $_SERVER['PROJECT_ROOT'];
        }

        if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
            return $_ENV['PROJECT_ROOT'];
        }

        if (file_exists('vendor')) {
            return (string) getcwd();
        }

        $dir = $rootDir = __DIR__;
        while (!file_exists($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    })());
}

$classLoader = require TEST_PROJECT_DIR . '/vendor/autoload.php';

(new Dotenv())->loadEnv(TEST_PROJECT_DIR . '/.env');

$pluginLoader = new StaticKernelPluginLoader($classLoader);
$kernel = new StaticAnalyzeKernel('phpstan_dev', true, $pluginLoader, 'phpstan-test-cache-id');
$kernel->boot();

return $classLoader;
