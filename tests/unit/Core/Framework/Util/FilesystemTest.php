<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\UtilException;
use Symfony\Component\Filesystem\Filesystem as Io;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Filesystem::class)]
class FilesystemTest extends TestCase
{
    private Io $io;

    private string $root;

    protected function setUp(): void
    {
        $this->io = new Io();

        $this->root = Path::join(sys_get_temp_dir(), $this->name());

        $this->io->mkdir($this->root);

        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        $this->io->remove($this->root);
    }

    public function testHas(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/file3.php');

        static::assertFalse($fs->has('file.php'));
        static::assertTrue($fs->has('file3.php'));
        static::assertTrue($fs->has('folder', 'file2.php'));
        static::assertTrue($fs->has('folder/file2.php'));
    }

    public function testHasFile(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/file3.php');

        static::assertFalse($fs->hasFile('file.php'));
        static::assertTrue($fs->hasFile('file3.php'));
        static::assertFalse($fs->hasFile('folder'));
        static::assertTrue($fs->hasFile('folder/file2.php'));
    }

    public function testPathsOutsideTheLocationAreNotAccessible(): void
    {
        $this->io->dumpFile($this->root . '/outside.txt', 'secret');
        $this->io->mkdir($this->root . '/app');

        $fs = new Filesystem($this->root . '/app');

        static::assertFalse($fs->has('../outside.txt'));
        static::assertFalse($fs->has('folder/../../outside.txt'));
        static::assertFalse($fs->has('..', 'outside.txt'));
        static::assertFalse($fs->hasFile('../outside.txt'));

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('../outside.txt', $this->root . '/app'));

        $fs->read('../outside.txt');
    }

    public function testPathsOutsideTheLocationAreAccessibleWithoutRestriction(): void
    {
        $this->io->dumpFile($this->root . '/node_modules/package/index.scss', 'scss');
        $this->io->mkdir($this->root . '/plugin');

        $fs = new Filesystem($this->root . '/plugin', restrictToLocation: false);

        static::assertTrue($fs->has('../node_modules/package/index.scss'));
        static::assertSame('scss', $fs->read('../node_modules/package/index.scss'));
    }

    public function testNestedAndNormalizedPathsInsideTheLocationAreAccessible(): void
    {
        $this->io->dumpFile($this->root . '/app/Resources/config/icon.png', 'icon');

        $fs = new Filesystem($this->root . '/app');

        static::assertTrue($fs->hasFile('Resources/config/icon.png'));
        static::assertTrue($fs->hasFile('Resources/./config/../config/icon.png'));
        static::assertTrue($fs->hasFile('Resources/config/../../Resources/config/icon.png'));
    }

    public function testAbsolutePathsAreResolvedInsideTheLocation(): void
    {
        $this->io->dumpFile($this->root . '/outside.txt', 'secret');
        $this->io->dumpFile($this->root . '/app/inside.txt', 'inside');

        $fs = new Filesystem($this->root . '/app');

        static::assertFalse($fs->has($this->root . '/outside.txt'));
        static::assertTrue($fs->has('/inside.txt'));
        static::assertSame('inside', $fs->read('/inside.txt'));
    }

    public function testRealPathThrowsExceptionWhenFileDoesNotExist(): void
    {
        $fs = new Filesystem($this->root);

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('file1.php', $this->root));

        $fs->realpath('file1.php');
    }

    public function testRealPath(): void
    {
        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file1.php');

        $fs = new Filesystem($this->root);

        static::assertSame($this->root . '/folder/file1.php', $fs->realpath('folder/../folder/file1.php'));
    }

    public function testPath(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/file1.php');

        static::assertSame($this->root . '/file1.php', $fs->path('file1.php'));
        static::assertSame($this->root . '/folder/file2.php', $fs->path('folder', 'file2.php'));
        static::assertSame($this->root . '/folder/file3.php', $fs->path('folder', 'file3.php')); // file does not exist but still works
    }

    public function testReadThrowsAnExceptionWhenFileDoesNotExist(): void
    {
        $fs = new Filesystem($this->root);

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('file.php', $this->root));

        $fs->read('file.php');
    }

    public function testRead(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->dumpFile($this->root . '/file.php', 'somecontent');

        static::assertSame('somecontent', $fs->read('file.php'));
    }

    public function testFindFiles(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file1.php');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/folder/file3.xml');

        $actual = array_map(
            static fn (\SplFileInfo $file) => $file->getPathname(),
            $fs->findFiles('*.php', 'folder')
        );

        sort($actual);
        static::assertSame(
            [
                $this->root . '/folder/file1.php',
                $this->root . '/folder/file2.php',
            ],
            $actual
        );
    }
}
