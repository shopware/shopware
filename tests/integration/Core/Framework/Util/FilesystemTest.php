<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util;

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

    public function testRealPathThrowsExceptionWhenFileDoesNotExist(): void
    {
        static::expectException(UtilException::class);

        $fs = new Filesystem($this->root);

        static::assertEquals($this->root . '/file1.php', $fs->realpath('file1.php'));
    }

    public function testRealPath(): void
    {
        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file1.php');

        $fs = new Filesystem($this->root);

        static::assertEquals($this->root . '/folder/file1.php', $fs->realpath('folder/../folder/file1.php'));
    }

    public function testPath(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/file1.php');

        static::assertEquals($this->root . '/file1.php', $fs->path('file1.php'));
        static::assertEquals($this->root . '/folder/file2.php', $fs->path('folder', 'file2.php'));
        static::assertEquals($this->root . '/folder/file3.php', $fs->path('folder', 'file3.php')); // file does not exist but still works
    }

    public function testReadThrowsAnExceptionWhenFileDoesNotExist(): void
    {
        static::expectException(UtilException::class);

        $fs = new Filesystem($this->root);

        $fs->read('file.php');
    }

    public function testRead(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->dumpFile($this->root . '/file.php', 'somecontent');

        static::assertEquals('somecontent', $fs->read('file.php'));
    }

    public function testFindFiles(): void
    {
        $fs = new Filesystem($this->root);

        $this->io->mkdir($this->root . '/folder');
        $this->io->touch($this->root . '/folder/file1.php');
        $this->io->touch($this->root . '/folder/file2.php');
        $this->io->touch($this->root . '/folder/file3.xml');

        $actual = array_map(
            fn (\SplFileInfo $file) => $file->getPathname(),
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

    public function testSymlinksPointingOutsideTheLocationAreNotAccessible(): void
    {
        $this->io->dumpFile($this->root . '/outside/secret.txt', 'secret');
        $this->io->mkdir($this->root . '/app');
        $this->io->symlink($this->root . '/outside/secret.txt', $this->root . '/app/icon.png');
        $this->io->symlink($this->root . '/outside', $this->root . '/app/Resources');

        $fs = new Filesystem($this->root . '/app');

        static::assertFalse($fs->has('icon.png'));
        static::assertFalse($fs->hasFile('icon.png'));
        static::assertFalse($fs->has('Resources/secret.txt'));
        static::assertNull($fs->readImage('icon.png'));

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('icon.png', $this->root . '/app'));

        $fs->read('icon.png');
    }

    public function testSymlinksInsideTheLocationAndSymlinkedLocationsAreAccessible(): void
    {
        $this->io->dumpFile($this->root . '/real-app/Resources/icon.png', 'icon');
        $this->io->symlink($this->root . '/real-app/Resources/icon.png', $this->root . '/real-app/icon.png');
        $this->io->symlink($this->root . '/real-app', $this->root . '/linked-app');

        $fs = new Filesystem($this->root . '/linked-app');

        static::assertTrue($fs->hasFile('Resources/icon.png'));
        static::assertTrue($fs->hasFile('icon.png'));
        static::assertSame('icon', $fs->read('icon.png'));
    }

    public function testReadImageReturnsAllowedImagesInsideTheLocation(): void
    {
        $this->io->dumpFile($this->root . '/app/Resources/icon.png', self::png());

        $fs = new Filesystem($this->root . '/app');

        static::assertSame(self::png(), $fs->readImage('Resources/icon.png'));
    }

    public function testReadImageRejectsMissingFilesDirectoriesAndPathsOutsideTheLocation(): void
    {
        $this->io->dumpFile($this->root . '/outside.png', self::png());
        $this->io->mkdir($this->root . '/app/Resources');

        $fs = new Filesystem($this->root . '/app');

        static::assertNull($fs->readImage('missing.png'));
        static::assertNull($fs->readImage('Resources'));
        static::assertNull($fs->readImage('../outside.png'));
        static::assertNull($fs->readImage($this->root . '/outside.png'));
    }

    public function testReadImageRejectsFilesThatAreNotAnAllowedImageType(): void
    {
        $this->io->dumpFile($this->root . '/app/.env', 'APP_SECRET=secret');
        $this->io->dumpFile($this->root . '/app/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $fs = new Filesystem($this->root . '/app');

        static::assertNull($fs->readImage('.env'));
        static::assertNull($fs->readImage('icon.svg'));
        static::assertNotNull($fs->readImage('icon.svg', ['image/svg+xml']));
    }

    public function testReadImageRejectsFilesLargerThanTheLimit(): void
    {
        $this->io->dumpFile($this->root . '/app/icon.png', self::png());

        $fs = new Filesystem($this->root . '/app');

        static::assertNull($fs->readImage('icon.png', maxSize: \strlen(self::png()) - 1));
        static::assertNotNull($fs->readImage('icon.png', maxSize: \strlen(self::png())));
    }

    public function testFileSize(): void
    {
        $this->io->dumpFile($this->root . '/app/file.txt', 'content');
        $this->io->dumpFile($this->root . '/outside.txt', 'secret');

        $fs = new Filesystem($this->root . '/app');

        static::assertSame(7, $fs->fileSize('file.txt'));

        static::expectExceptionObject(UtilException::cannotFindFileInFilesystem('../outside.txt', $this->root . '/app'));

        $fs->fileSize('../outside.txt');
    }

    private static function png(): string
    {
        // 1x1 transparent PNG
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true);
    }
}
