<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Shopware\Core\Profiling\Doctrine\DebugStack;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

class KernelLifecycleManager
{
    /**
     * @var string
     */
    protected static $class;

    /**
     * @var KernelInterface|null
     */
    protected static $kernel;

    /**
     * @var ClassLoader
     */
    protected static $classLoader;

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
    public static function getKernel(): KernelInterface
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
    public static function createBrowser(KernelInterface $kernel, bool $enableReboot = false): KernelBrowser
    {
        /** @var KernelBrowser $apiBrowser */
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
    public static function bootKernel(): KernelInterface
    {
        self::ensureKernelShutdown();

        static::$kernel = static::createKernel();
        static::$kernel->boot();
        static::$kernel->getContainer()->get(Connection::class)->getConfiguration()->setSQLLogger(new DebugStack());
        MemoryAdapterFactory::resetInstances();

        return static::$kernel;
    }

    public static function createKernel(?string $kernelClass = null): KernelInterface
    {
        if ($kernelClass === null) {
            if (static::$class === null) {
                static::$class = static::getKernelClass();
            }

            $kernelClass = static::$class;
        }

        if (isset($_ENV['APP_ENV'])) {
            $env = $_ENV['APP_ENV'];
        } elseif (isset($_SERVER['APP_ENV'])) {
            $env = $_SERVER['APP_ENV'];
        } else {
            $env = 'test';
        }

        if (isset($_ENV['APP_DEBUG'])) {
            $debug = (bool) $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = (bool) $_SERVER['APP_DEBUG'];
        } else {
            $debug = true;
        }

        if (self::$classLoader === null) {
            throw new \InvalidArgumentException('No class loader set. Please call KernelLifecycleManager::prepare');
        }

        $pluginLoader = new DbalKernelPluginLoader(self::$classLoader, null, $kernelClass::getConnection());

        // This hash MUST be constant as long as NEXT-5273 is not resolved.
        // Otherwise tests using a dataprovider wither services (such as JsonSalesChannelEntityEncoderTest)
        // will fail randomly
        $cacheId = 'h8f3f0ee9c61829627676afd6294bb029';

        return new $kernelClass($env, $debug, $pluginLoader, $cacheId);
    }

    /**
     * @throws \RuntimeException
     * @throws \LogicException
     */
    public static function getKernelClass(): string
    {
        if (!isset($_SERVER['KERNEL_CLASS']) && !isset($_ENV['KERNEL_CLASS'])) {
            throw new \LogicException(
                sprintf(
                    'You must set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel in phpunit.xml / phpunit.xml.dist or override the %1$s::createKernel() or %1$s::getKernelClass() method.',
                    static::class
                )
            );
        }

        if (!class_exists($class = $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'])) {
            throw new \RuntimeException(
                sprintf(
                    'Class "%s" doesn\'t exist or cannot be autoloaded. Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel or override the %s::createKernel() method.',
                    $class,
                    static::class
                )
            );
        }

        return $class;
    }

    /**
     * Shuts the kernel down if it was used in the test.
     */
    private static function ensureKernelShutdown(): void
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
}
