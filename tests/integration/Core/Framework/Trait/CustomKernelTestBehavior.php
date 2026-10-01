<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Trait;

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 *
 * @template KernelClass of Kernel
 */
#[Package('framework')]
trait CustomKernelTestBehavior
{
    /**
     * @var KernelClass
     */
    private static Kernel $kernel;

    public static function loadKernel(): void
    {
        $oldKernelClass = KernelFactory::$kernelClass;
        KernelFactory::$kernelClass = self::getKernelClass();

        try {
            /** @var KernelClass $kernel */
            $kernel = KernelLifecycleManager::createKernel(
                self::getKernelClass(),
                cacheId: self::getKernelCacheId(),
            );
        } finally {
            KernelFactory::$kernelClass = $oldKernelClass;
        }

        $kernel->boot();
        self::$kernel = $kernel;
    }

    public static function unloadKernel(): void
    {
        self::$kernel->shutdown();
    }

    /**
     * This results in the test container, with all private services public
     */
    private static function getContainer(): ContainerInterface
    {
        $container = self::$kernel->getContainer();

        if (!$container->has('test.service_container')) {
            throw new \RuntimeException('Unable to run tests against kernel without test.service_container');
        }

        /** @var ContainerInterface $testContainer */
        $testContainer = $container->get('test.service_container');

        return $testContainer;
    }

    /**
     * @return class-string<KernelClass>
     */
    abstract private static function getKernelClass(): string;

    private static function getKernelCacheId(): string
    {
        return 'h8f3f0ee9c61829627676afd6294bb029';
    }
}
