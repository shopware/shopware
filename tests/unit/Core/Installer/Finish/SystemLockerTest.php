<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\SystemLocker;

/**
 * @internal
 *
 * @covers \Shopware\Core\Installer\Finish\SystemLocker
 */
class SystemLockerTest extends TestCase
{
    public function testLock(): void
    {
        $locker = new SystemLocker(__DIR__);
        $locker->lock();

        static::assertFileExists(__DIR__ . '/install.lock');
        unlink(__DIR__ . '/install.lock');
    }
}
