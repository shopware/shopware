<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;

class KernelLifecycleManagerTest extends TestCase
{
    public const BUILD_AGAINST_FILE_HASH = '8da372041290bb36d3e6f4c3bc56e8236e9794d1';

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

        KernelLifecycleManager::bootKernel();

        $newKernel = KernelLifecycleManager::getKernel();
        $newConnection = Kernel::getConnection();

        static::assertNotSame(spl_object_hash($oldKernel), spl_object_hash($newKernel));
        static::assertNotSame(spl_object_hash($oldConnection), spl_object_hash($newConnection));
        static::assertNotSame(spl_object_hash($oldContainer), spl_object_hash($newKernel->getContainer()));
    }
}
