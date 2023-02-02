<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;
use staabm\PHPStanDba\QueryReflection\PdoQueryReflector;
use staabm\PHPStanDba\QueryReflection\QueryReflection;
use staabm\PHPStanDba\QueryReflection\RuntimeConfiguration;
use Symfony\Component\Dotenv\Dotenv;

if (!\defined('TEST_PROJECT_DIR')) {
    \define('TEST_PROJECT_DIR', (function (): string {
        if (isset($_SERVER['PROJECT_ROOT']) && file_exists($_SERVER['PROJECT_ROOT'])) {
            return $_SERVER['PROJECT_ROOT'];
        }

        if (isset($_ENV['PROJECT_ROOT']) && file_exists($_ENV['PROJECT_ROOT'])) {
            return $_ENV['PROJECT_ROOT'];
        }

        if (file_exists('vendor') && (file_exists('.env') || file_exists('.env.dist'))) {
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

$_ENV['PROJECT_ROOT'] = $_SERVER['PROJECT_ROOT'] = TEST_PROJECT_DIR;
$classLoader = require TEST_PROJECT_DIR . '/vendor/autoload.php';

if (class_exists(Dotenv::class) && (file_exists(TEST_PROJECT_DIR . '/.env.local.php') || file_exists(TEST_PROJECT_DIR . '/.env') || file_exists(TEST_PROJECT_DIR . '/.env.dist'))) {
    (new Dotenv())->usePutenv()->bootEnv(TEST_PROJECT_DIR . '/.env');
}

try {
    $config = new RuntimeConfiguration();
    $config->stringifyTypes(true);

    /** @var \PDO $pdo */
    $pdo = MySQLFactory::create()->getWrappedConnection();
    QueryReflection::setupReflector(
        new PdoQueryReflector($pdo),
        $config
    );
} catch (\Exception $e) {
    // if DB is not set up the phpstan-dba extension won't work
    // in that case we ignore it and skip the extension, during CI it will run at last
    $config = new RuntimeConfiguration();
    $config->stringifyTypes(true);

    QueryReflection::setupReflector(
        new NullReflector(),
        $config
    );
}

$databaseUrl = $_SERVER['DATABASE_URL'];

$_SERVER['DATABASE_URL'] = Kernel::PLACEHOLDER_DATABASE_URL;
$pluginLoader = new StaticKernelPluginLoader($classLoader);
$kernel = new StaticAnalyzeKernel('phpstan_dev', true, $pluginLoader, 'phpstan-test-cache-id');
$kernel->boot();

$_SERVER['DATABASE_URL'] = $databaseUrl;

return $classLoader;
