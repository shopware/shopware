<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Symfony\Component\Dotenv\Dotenv;

if (!\defined('TEST_PROJECT_DIR')) {
    \define('TEST_PROJECT_DIR', (function (): string {
        if (isset($_SERVER['PROJECT_ROOT']) && \is_dir($_SERVER['PROJECT_ROOT'])) {
            return $_SERVER['PROJECT_ROOT'];
        }

        if (isset($_ENV['PROJECT_ROOT']) && \is_dir($_ENV['PROJECT_ROOT'])) {
            return $_ENV['PROJECT_ROOT'];
        }

        if (\is_file('vendor') && (\is_file('.env') || \is_file('.env.dist'))) {
            return (string) getcwd();
        }

        $dir = $rootDir = __DIR__;
        while (!\is_dir($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    })());
}

$_ENV['PROJECT_ROOT'] = $_SERVER['PROJECT_ROOT'] = TEST_PROJECT_DIR;
$classLoader = require TEST_PROJECT_DIR . '/vendor/autoload.php';

if (class_exists(Dotenv::class) && (\is_file(TEST_PROJECT_DIR . '/.env.local.php') || \is_file(TEST_PROJECT_DIR . '/.env') || \is_file(TEST_PROJECT_DIR . '/.env.dist'))) {
    (new Dotenv())->usePutenv()->bootEnv(TEST_PROJECT_DIR . '/.env');
}

$pluginLoader = new ComposerPluginLoader($classLoader);
KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

/** @var StaticAnalyzeKernel $kernel */
$kernel = KernelFactory::create(
    'phpstan_dev',
    true,
    $classLoader,
    $pluginLoader
);

$kernel->boot();

return $classLoader;
