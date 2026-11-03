<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StaticFilesystem::class)]
class StaticFilesystemTest extends TestCase
{
    public function testHas(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertTrue($fs->has('one.php'));
        static::assertFalse($fs->has('two.php'));
    }

    public function testHasFile(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertTrue($fs->hasFile('one.php'));
        static::assertFalse($fs->hasFile('two.php'));
    }

    public function testFileSize(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame(8, $fs->fileSize('one.php'));
    }

    public function testReadImage(): void
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true);

        $fs = new StaticFilesystem([
            'icon.png' => $png,
            'one.php' => 'content1',
        ]);

        static::assertSame($png, $fs->readImage('icon.png'));
        static::assertNull($fs->readImage('one.php'));
        static::assertNull($fs->readImage('two.png'));
    }

    public function testPath(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame('/app-root/one.php', $fs->path('one.php'));
        static::assertSame('/app-root/two.php', $fs->path('two.php'));
    }

    public function testReadThrowsExceptionWhenFileDoesNotExist(): void
    {
        static::expectException(UtilException::class);

        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        $fs->read('two.php');
    }

    public function testRead(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame('content1', $fs->read('one.php'));
    }

    public function testFindFiles(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame([], $fs->findFiles('*.php', '/'));
    }
}
