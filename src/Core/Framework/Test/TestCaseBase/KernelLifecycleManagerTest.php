<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;

/**
 * @group skip-paratest
 */
class KernelLifecycleManagerTest extends TestCase
{
    public const BUILD_AGAINST_FILE_HASH = '90a76c4f0b3803319284ec45500bd140cbe0f543';

    public function testIfTheManagerNeedsAnUpdate(): void
    {
        $kernelTestCaseFileName = TEST_PROJECT_DIR . '/vendor/symfony/framework-bundle/Test/KernelTestCase.php';

        static::assertFileExists($kernelTestCaseFileName);
        static::assertSame(
            self::BUILD_AGAINST_FILE_HASH,
            sha1_file($kernelTestCaseFileName),
            sprintf('You need to update the class KernelTestCase from %s and update the local hash', $kernelTestCaseFileName)
        );
    }

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
