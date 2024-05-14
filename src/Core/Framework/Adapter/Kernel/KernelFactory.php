<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Kernel;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheIdLoader;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Shopware\Core\Framework\Adapter\Kernel\KernelFactory
 *      Shopware\Core\Kernel
 *          Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel (http caching)
 *              Shopware\Core\Framework\Adapter\Kernel\HttpKernel (runs request transformer)
 *                  Shopware\Storefront\Controller\Any
 *
 * @final
 */
#[Package('core')]
class KernelFactory
{
    /**
     * @var class-string<Kernel>
     */
    public static string $kernelClass = Kernel::class;

    public static function create(
        string $environment,
        bool $debug,
        ClassLoader $classLoader,
        ?KernelPluginLoader $pluginLoader = null,
        ?Connection $connection = null
    ): HttpKernelInterface {
        if (InstalledVersions::isInstalled('shopware/platform')) {
            $shopwareVersion = InstalledVersions::getVersion('shopware/platform')
                . '@' . InstalledVersions::getReference('shopware/platform');
        } else {
            $shopwareVersion = InstalledVersions::getVersion('shopware/core')
                . '@' . InstalledVersions::getReference('shopware/core');
        }

        $middlewares = [];
        if (\PHP_SAPI !== 'cli' && $environment !== 'prod' && InstalledVersions::isInstalled('symfony/doctrine-bridge')) {
            $middlewares = [new ProfilingMiddleware()];
        }

        $connection = $connection ?? MySQLFactory::create($middlewares);

        $pluginLoader = $pluginLoader ?? new DbalKernelPluginLoader($classLoader, null, $connection);

        $storage = new MySQLKeyValueStorage($connection);
        $cacheId = (new CacheIdLoader($storage))->load();

        /** @var KernelInterface $kernel */
        $kernel = new static::$kernelClass(
            $environment,
            $debug,
            $pluginLoader,
            $cacheId,
            $shopwareVersion,
            $connection,
            self::getProjectDir()
        );

        return $kernel;
    }

    private static function getProjectDir(): string
    {
        if ($dir = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? false) {
            return $dir;
        }

        $r = new \ReflectionClass(self::class);

        /** @var string $dir */
        $dir = $r->getFileName();

        $dir = $rootDir = \dirname($dir);
        while (!file_exists($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return $rootDir;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }
}
