<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Installer\Finish;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class SystemLockerTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-system-locker-test', true);
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testLock(): void
    {
        $locker = new SystemLocker($this->projectDir);
        $locker->lock();

        static::assertFileExists($this->projectDir . '/install.lock');
    }

    public function testLockFileContainsTimestamp(): void
    {
        $locker = new SystemLocker($this->projectDir);
        $locker->lock();

        $content = $this->filesystem->readFile($this->projectDir . '/install.lock');
        // The file should contain a timestamp in YmdHi format
        static::assertMatchesRegularExpression('/^\d{12}$/', $content);
    }
}
