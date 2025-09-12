<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Finish\SystemLocker;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(SystemLocker::class)]
class SystemLockerTest extends TestCase
{
    use EnvTestBehaviour;

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__ . '/install.lock');
    }

    public function testLock(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileExists(__DIR__ . '/install.lock');
    }

    public function testLockFileContainsTimestamp(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        $content = file_get_contents(__DIR__ . '/install.lock');
        static::assertNotEmpty($content);
        // The file should contain a timestamp in YmdHi format
        static::assertMatchesRegularExpression('/^\d{12}$/', $content);
    }

    public function testLockSkippedWhenEnvironmentVariableSet(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_WEBINSTALLER' => '1']);

        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileDoesNotExist(__DIR__ . '/install.lock');
    }
}
