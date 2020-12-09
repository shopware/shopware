<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Checkers;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Update\Checkers\WriteableCheck;
use Shopware\Core\Framework\Update\Services\Filesystem;

class WritableCheckTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCheck(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $writableCheck = new WriteableCheck($filesystem, '/tmp');

        $checkFiles = [
            'foo',
            'foo/bar',
        ];

        $filesystem->expects(static::exactly(\count($checkFiles)))
            ->method('checkSingleDirectoryPermissions')
            ->withConsecutive(
                [static::equalTo('/tmp/foo'), static::equalTo(true)],
                [static::equalTo('/tmp/foo/bar'), static::equalTo(true)]
            )
            ->willReturn([]);

        $actual = $writableCheck->check($checkFiles)->jsonSerialize();
        static::assertTrue($actual['result']);
    }

    public function testCheckNoPermissions(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $writableCheck = new WriteableCheck($filesystem, '/tmp');

        $checkFiles = [
            'foo',
            'not-writable',
            'also-not-writable',
        ];

        $filesystem->expects(static::exactly(\count($checkFiles)))
            ->method('checkSingleDirectoryPermissions')
            ->withConsecutive(
                [static::equalTo('/tmp/foo'), static::equalTo(true)],
                [static::equalTo('/tmp/not-writable'), static::equalTo(true)],
                [static::equalTo('/tmp/also-not-writable'), static::equalTo(true)]
            )
            ->willReturnOnConsecutiveCalls([], ['/tmp/not-writable'], ['/tmp/also-not-writable']);

        $actual = $writableCheck->check($checkFiles)->jsonSerialize();
        static::assertFalse($actual['result']);
        static::assertSame('/tmp/not-writable<br>/tmp/also-not-writable', $actual['vars']['failedDirectories']);
    }

    public function testSupports(): void
    {
        $writableCheck = new WriteableCheck($this->createMock(Filesystem::class), '/tmp');

        static::assertTrue($writableCheck->supports('writable'));
        static::assertFalse($writableCheck->supports('licensecheck'));
        static::assertFalse($writableCheck->supports('phpversion'));
        static::assertFalse($writableCheck->supports('mysqlversion'));
        static::assertFalse($writableCheck->supports(''));
    }
}
