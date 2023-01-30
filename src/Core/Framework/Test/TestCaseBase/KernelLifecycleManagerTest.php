<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @group skip-paratest
 */
class KernelLifecycleManagerTest extends TestCase
{
    public function testIfTheKernelClassIsShopware(): void
    {
        static::assertInstanceOf(Kernel::class, KernelLifecycleManager::getKernel());
    }

    public function testARebootIsPossible(): void
    {
        $oldKernel = KernelLifecycleManager::getKernel();
        $oldConnection = Kernel::getConnection();
        $oldContainer = $oldKernel->getContainer();

        KernelLifecycleManager::bootKernel(false);

        $newKernel = KernelLifecycleManager::getKernel();
        $newConnection = Kernel::getConnection();

        static::assertNotSame(spl_object_hash($oldKernel), spl_object_hash($newKernel));
        static::assertNotSame(spl_object_hash($oldConnection), spl_object_hash($newConnection));
        static::assertNotSame(spl_object_hash($oldContainer), spl_object_hash($newKernel->getContainer()));
    }

    /*
     * regression test - KernelLifecycleManager::bootKernel used to keep all connections open, due to remaining references.
     * This resulted in case of mariadb in a max connection limit error after 100 connections/calls to bootKernel.
     */
    public function testNoConnectionLeak(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            KernelLifecycleManager::bootKernel(true);
        }

        static::assertTrue(true);
    }
}
