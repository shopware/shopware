<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelLifecycleManagerTest extends TestCase
{
    const BUILD_AGAINST_FILE_HASH = 'd0645f26944b1ba60f75d34b5a06f9e84ce1f852';

    public function testIfTheManagerNeedsAnUpdate()
    {
        $reflection = new \ReflectionClass(KernelTestCase::class);

        $this->assertFileExists($reflection->getFileName());

        $this->assertSame(
            self::BUILD_AGAINST_FILE_HASH,
            sha1_file($reflection->getFileName()),
            sprintf('You need to update the class %s and update the local hash', KernelLifecycleManager::class)
        );
    }

    public function testIfTheKernelClassIsShopware()
    {
        $this->assertInstanceOf(Kernel::class, KernelLifecycleManager::getKernel());
    }

    public function testARebootIsPossible()
    {
        $oldKernel = KernelLifecycleManager::getKernel();
        $oldConnection = Kernel::getConnection();
        $oldContainer = $oldKernel->getContainer();

        KernelLifecycleManager::bootKernel();

        $newKernel = KernelLifecycleManager::getKernel();
        $newConnection = Kernel::getConnection();

        $this->assertNotSame(spl_object_hash($oldKernel), spl_object_hash($newKernel));
        $this->assertNotSame(spl_object_hash($oldConnection), spl_object_hash($newConnection));
        $this->assertNotSame(spl_object_hash($oldContainer), spl_object_hash($newKernel->getContainer()));
    }
}
