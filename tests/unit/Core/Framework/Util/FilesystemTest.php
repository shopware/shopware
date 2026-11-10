<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Filesystem::class)]
class FilesystemTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = (string) realpath(__DIR__ . '/fixtures/filesystem');
    }

    public function testHas(): void
    {
        $fs = new Filesystem($this->root);

        static::assertFalse($fs->has('missing.txt'));
        static::assertTrue($fs->has('file3.txt'));
        static::assertTrue($fs->has('folder', 'file2.txt'));
        static::assertTrue($fs->has('folder/file2.txt'));
    }

    public function testHasFile(): void
    {
        $fs = new Filesystem($this->root);

        static::assertFalse($fs->hasFile('missing.txt'));
        static::assertTrue($fs->hasFile('file3.txt'));
        static::assertFalse($fs->hasFile('folder'));
        static::assertTrue($fs->hasFile('folder/file2.txt'));
    }

    public function testRealPathThrowsExceptionWhenFileDoesNotExist(): void
    {
        $fs = new Filesystem($this->root);

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('missing.txt', $this->root));

        $fs->realpath('missing.txt');
    }

    public function testRealPath(): void
    {
        $fs = new Filesystem($this->root);

        static::assertSame($this->root . '/folder/file1.txt', $fs->realpath('folder/../folder/file1.txt'));
    }

    public function testPath(): void
    {
        $fs = new Filesystem($this->root);

        static::assertSame($this->root . '/file3.txt', $fs->path('file3.txt'));
        static::assertSame($this->root . '/folder/file2.txt', $fs->path('folder', 'file2.txt'));
        static::assertSame($this->root . '/folder/missing.txt', $fs->path('folder', 'missing.txt')); // file does not exist but still works
    }

    public function testReadThrowsAnExceptionWhenFileDoesNotExist(): void
    {
        $fs = new Filesystem($this->root);

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('missing.txt', $this->root));

        $fs->read('missing.txt');
    }

    public function testRead(): void
    {
        $fs = new Filesystem($this->root);

        static::assertSame('somecontent', $fs->read('file.txt'));
    }

    public function testFindFiles(): void
    {
        $fs = new Filesystem($this->root);

        $actual = array_map(
            static fn (\SplFileInfo $file) => $file->getPathname(),
            $fs->findFiles('*.txt', 'folder')
        );

        sort($actual);
        static::assertSame(
            [
                $this->root . '/folder/file1.txt',
                $this->root . '/folder/file2.txt',
            ],
            $actual
        );
    }
}
