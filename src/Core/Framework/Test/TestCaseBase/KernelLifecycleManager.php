<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class KernelLifecycleManager
{
    /**
     * @var class-string<Kernel>
     */
    protected static $class;

    /**
     * @var Kernel|null
     */
    protected static $kernel;

    /**
     * @var ClassLoader
     */
    protected static $classLoader;

    /**
     * @var Connection|null
     */
    protected static $connection;

    public static function prepare(ClassLoader $classLoader): void
    {
        self::$classLoader = $classLoader;
    }

    public static function getClassLoader(): ClassLoader
    {
        return self::$classLoader;
    }

    /**
     * Get the currently active kernel
     */
    public static function getKernel(): Kernel
    {
        if (static::$kernel) {
            static::$kernel->boot();

            return static::$kernel;
        }

        return static::bootKernel();
    }

    /**
     * Create a web client with the default kernel and disabled reboots
     */
    public static function createBrowser(KernelInterface $kernel, bool $enableReboot = false): TestBrowser
    {
        /** @var TestBrowser $apiBrowser */
        $apiBrowser = $kernel->getContainer()->get('test.browser');

        if ($enableReboot) {
            $apiBrowser->enableReboot();
        } else {
            $apiBrowser->disableReboot();
        }

        return $apiBrowser;
    }

    /**
     * Boots the Kernel for this test.
     */
    public static function bootKernel(bool $reuseConnection = true, string $cacheId = 'h8f3f0ee9c61829627676afd6294bb029'): Kernel
    {
        self::ensureKernelShutdown();

        static::$kernel = static::createKernel(null, $reuseConnection, $cacheId);
        static::$kernel->boot();
        MemoryAdapterFactory::resetInstances();

        return static::$kernel;
    }

    /**
     * @param class-string<Kernel>|null $kernelClass
     */
    public static function createKernel(?string $kernelClass = null, bool $reuseConnection = true, string $cacheId = 'h8f3f0ee9c61829627676afd6294bb029', ?string $projectDir = null): Kernel
    {
        if ($kernelClass === null) {
            if (static::$class === null) {
                static::$class = static::getKernelClass();
            }

            $kernelClass = static::$class;
        }

        $env = EnvironmentHelper::getVariable('APP_ENV', 'test');
        $debug = (bool) EnvironmentHelper::getVariable('APP_DEBUG', true);

        if (self::$classLoader === null) {
            throw new \InvalidArgumentException('No class loader set. Please call KernelLifecycleManager::prepare');
        }

        try {
            $existingConnection = null;
            if ($reuseConnection) {
                $existingConnection = self::getConnection();

                try {
                    $existingConnection->fetchOne('SELECT 1');
                } catch (\Throwable) {
                    // The connection is closed
                    $existingConnection = null;
                }
            }
            if ($existingConnection === null) {
                $existingConnection = self::$connection = $kernelClass::getConnection();
            }

            // force connection to database
            $existingConnection->fetchOne('SELECT 1');

            $pluginLoader = new DbalKernelPluginLoader(self::$classLoader, null, $existingConnection);
        } catch (\Throwable) {
            // if we don't have database yet, we'll boot the kernel without plugins
            $pluginLoader = new StaticKernelPluginLoader(self::$classLoader);
        }

        return new $kernelClass($env, $debug, $pluginLoader, $cacheId, null, $existingConnection, $projectDir);
    }

    /**
     * @return class-string<Kernel>
     */
    public static function getKernelClass(): string
    {
        if (!class_exists($class = (string) EnvironmentHelper::getVariable('KERNEL_CLASS', Kernel::class))) {
            throw new \RuntimeException(
                sprintf(
                    'Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the %s::createKernel() method.',
                    $class,
                    static::class
                )
            );
        }

        if (!is_a($class, Kernel::class, true)) {
            throw new \RuntimeException(
                sprintf(
                    'Class "%s" must extend "%s". Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the %s::createKernel() method.',
                    $class,
                    Kernel::class,
                    static::class
                )
            );
        }

        return $class;
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    public static function ensureKernelShutdown(): void
    {
        if (static::$kernel === null) {
            return;
        }

        $container = static::$kernel->getContainer();
        static::$kernel->shutdown();

        if ($container instanceof ResetInterface) {
            $container->reset();
        }

        static::$kernel = null;
    }

    public static function getConnection(): Connection
    {
        if (!static::$connection) {
            static::$connection = MySQLFactory::create();
        }

        return static::$connection;
    }
}
