<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Redis;

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @template KernelClass of Kernel
 */
#[Package('core')]
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
        /** @var KernelClass $kernel */
        $kernel = KernelLifecycleManager::createKernel(self::getKernelClass());
        KernelFactory::$kernelClass = $oldKernelClass; // Do not forget to recover default kernel class!

        $kernel->boot();
        self::$kernel = $kernel;
    }

    public static function unloadKernel(): void
    {
        self::$kernel->shutdown();
    }

    /**
     * @return class-string<KernelClass>
     */
    abstract private static function getKernelClass(): string;
}
