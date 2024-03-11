<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Checkers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Checkers\WriteableCheck;
use Shopware\Core\Framework\Update\Services\Filesystem;

/**
 * @internal
 */
#[CoversClass(WriteableCheck::class)]
class WritableCheckTest extends TestCase
{
    public function testCheck(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $writableCheck = new WriteableCheck($filesystem, '/tmp');

        $checkFiles = [
            '/',
        ];

        $filesystem->expects(static::exactly(\count($checkFiles)))
            ->method('checkSingleDirectoryPermissions')
            ->with(
                static::equalTo('/tmp/'),
                static::equalTo(true)
            )
            ->willReturn([]);

        $actual = $writableCheck->check()->jsonSerialize();
        static::assertTrue($actual['result']);
    }

    public function testCheckNoPermissions(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $writableCheck = new WriteableCheck($filesystem, '/tmp');

        $filesystem->expects(static::exactly(1))
            ->method('checkSingleDirectoryPermissions')
            ->with(
                static::equalTo('/tmp/'),
                static::equalTo(true),
            )
            ->willReturnOnConsecutiveCalls(['/tmp/not-writable<br>/tmp/also-not-writable']);

        $actual = $writableCheck->check()->jsonSerialize();
        static::assertFalse($actual['result']);
        static::assertSame('/tmp/not-writable<br>/tmp/also-not-writable', $actual['vars']['failedDirectories']);
    }
}
