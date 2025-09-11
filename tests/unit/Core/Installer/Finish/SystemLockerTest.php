<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SystemLocker::class)]
class SystemLockerTest extends TestCase
{
    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove([
            __DIR__ . '/var/install.lock',
            __DIR__ . '/var',
        ]);
    }

    public function testLock(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileExists(__DIR__ . '/var/install.lock');
        static::assertDirectoryExists(__DIR__ . '/var');
    }

    public function testLockCreatesVarDirectory(): void
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/var');

        static::assertDirectoryDoesNotExist(__DIR__ . '/var');

        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertDirectoryExists(__DIR__ . '/var');
        static::assertFileExists(__DIR__ . '/var/install.lock');
    }

    public function testLockWithExistingVarDirectory(): void
    {
        mkdir(__DIR__ . '/var', 0777, true);

        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertDirectoryExists(__DIR__ . '/var');
        static::assertFileExists(__DIR__ . '/var/install.lock');
    }

    public function testLockFileContainsTimestamp(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        $content = file_get_contents(__DIR__ . '/var/install.lock');
        static::assertNotEmpty($content);
        // The file should contain a timestamp in YmdHi format
        static::assertMatchesRegularExpression('/^\d{12}$/', $content);
    }
}
