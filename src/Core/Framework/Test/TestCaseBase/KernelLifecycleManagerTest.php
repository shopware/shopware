<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelLifecycleManagerTest extends TestCase
{
    public const BUILD_AGAINST_FILE_HASH = '87f9dedec48135c83908515f50c55aec10de6471';

    public function testIfTheManagerNeedsAnUpdate(): void
    {
        $reflection = new \ReflectionClass(KernelTestCase::class);

        static::assertFileExists($reflection->getFileName());

        static::assertSame(
            self::BUILD_AGAINST_FILE_HASH,
            sha1_file($reflection->getFileName()),
            sprintf('You need to update the class %s and update the local hash', KernelLifecycleManager::class)
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
