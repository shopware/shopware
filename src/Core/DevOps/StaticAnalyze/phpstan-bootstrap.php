<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Composer\Autoload\ClassLoader;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Dotenv\Dotenv;

if (!\defined('TEST_PROJECT_DIR')) {
    \define('TEST_PROJECT_DIR', (static function (): string {
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

/*
 * The Danger rules (src/Core/DevOps/StaticAnalyze/Danger) type against the danger-php package
 * from vendor-bin. Only its own Danger\ namespace is registered — appended, never the package's
 * full vendor autoloader, which would take priority over the root vendor for the symfony
 * packages both ship. Without the install the Danger paths are excluded via phpstan-paths.php.
 */
$dangerSrc = TEST_PROJECT_DIR . '/vendor-bin/danger-php/vendor/shyim/danger-php/src';
if (is_dir($dangerSrc)) {
    $dangerClassLoader = new ClassLoader();
    $dangerClassLoader->addPsr4('Danger\\', $dangerSrc);
    $dangerClassLoader->register();
}

/*
 * Same for the custom Rector rules (src/Core/DevOps/StaticAnalyze/Rector), which type against
 * the rector package from vendor-bin. Only the Rector\ classes are registered from rector's
 * bundled classmap — appended, never rector's full autoloader, which ships its own copies of
 * nikic/php-parser and phpstan that must not shadow the root vendor's. Without the install the
 * Rector paths are excluded via phpstan-paths.php.
 */
$rectorClassMap = TEST_PROJECT_DIR . '/vendor-bin/rector/vendor/rector/rector/vendor/composer/autoload_classmap.php';
if (is_file($rectorClassMap)) {
    $rectorClassLoader = new ClassLoader();
    $rectorClassLoader->addClassMap(array_filter(
        require $rectorClassMap,
        static fn (string $class): bool => str_starts_with($class, 'Rector\\'),
        \ARRAY_FILTER_USE_KEY,
    ));
    $rectorClassLoader->register();
}

$cacheDir = TEST_PROJECT_DIR . '/var/cache/static_phpstan_dev';
$containerXml = $cacheDir . '/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml';
$containerPhp = $cacheDir . '/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.php';

$cache = new ConfigCache($containerPhp, true);

if (is_file($containerXml) && $cache->isFresh()) {
    return $classLoader;
}

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
