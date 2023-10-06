<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;

/**
 * @internal
 */
class InMemoryFilesystemAdapterTest extends TestCase
{
    public function testDelete(): void
    {
        $memoryFilesystemAdapter = new MemoryFilesystemAdapter();
        $fs = new Filesystem($memoryFilesystemAdapter);
        $fs->write('a.txt', 'foo');
        static::assertTrue($fs->fileExists('a.txt'));
        static::assertFalse($fs->directoryExists('a.txt'));
        $memoryFilesystemAdapter->deleteEverything();

        static::assertFalse($fs->fileExists('a.txt'));
        static::assertFalse($fs->directoryExists('a.txt'));

        $fs->write('a.txt', 'foo');
        static::assertTrue($fs->fileExists('a.txt'));
        $fs->delete('a.txt');
        static::assertFalse($fs->fileExists('a.txt'));
    }

    public function testDeleteDirectory(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->write('test/a.txt', 'foo');
        static::assertTrue($fs->directoryExists('test'));
        $fs->deleteDirectory('test');
        static::assertFalse($fs->directoryExists('test'));
    }

    public function testMoveDirectory(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        static::assertFalse($fs->fileExists('foo/a.txt'));
        static::assertFalse($fs->directoryExists('foo'));
        $fs->write('foo/a.txt', 'foo');

        static::assertTrue($fs->directoryExists('foo'));

        $fs->move('foo', 'foo2');

        static::assertFalse($fs->directoryExists('foo'));
        static::assertTrue($fs->directoryExists('foo2'));

        $fs->write('foo3', 'foo');
        static::expectException(UnableToMoveFile::class);
        static::expectExceptionMessage('Unable to move file from /foo2 to /foo3');
        $fs->move('foo2', 'foo3');
    }

    public function testMoveFile(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->write('foo.txt', 'bla');
        $fs->move('foo.txt', 'blaa.txt');
        static::assertTrue($fs->fileExists('blaa.txt'));

        $fs->write('exists.txt', 'bla');
        static::expectException(UnableToMoveFile::class);
        $fs->move('blaa.txt', 'exists.txt');
    }

    public function testWriteStream(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $fs->writeStream('a.txt', fopen($tmpFile, 'rb'));

        static::assertTrue($fs->fileExists('a.txt'));
        static::assertSame('test', $fs->read('a.txt'));
        static::assertSame('test', stream_get_contents($fs->readStream('a.txt')));

        unlink($tmpFile);
    }

    public function testReadNotExistingFile(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        static::expectException(UnableToReadFile::class);
        $fs->read('foo');
    }

    public function testReadStreamNotExistingFile(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        static::expectException(UnableToReadFile::class);
        $fs->readStream('foo');
    }

    public function testCreateDir(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->createDirectory('foo');
        static::assertTrue($fs->directoryExists('foo'));
        $fs->deleteDirectory('foo');
        static::assertFalse($fs->directoryExists('foo'));
    }

    public function testFileSize(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a.txt', 'test');
        static::assertSame(4, $fs->fileSize('a.txt'));

        static::expectException(UnableToRetrieveMetadata::class);
        $fs->fileSize('bla');
    }

    public function testVisibility(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a.txt', 'test');
        static::assertSame(Visibility::PUBLIC, $fs->visibility('a.txt'));

        static::expectException(UnableToRetrieveMetadata::class);
        $fs->visibility('bla');
    }

    public function testSetVisibility(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a.txt', 'test');
        static::assertSame(Visibility::PUBLIC, $fs->visibility('a.txt'));
        $fs->setVisibility('a.txt', Visibility::PRIVATE);
        static::assertSame(Visibility::PRIVATE, $fs->visibility('a.txt'));

        static::expectException(UnableToSetVisibility::class);
        $fs->setVisibility('bla', Visibility::PRIVATE);
    }

    public function testMimeType(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a.txt', 'test');

        static::assertSame('text/plain', $fs->mimeType('a.txt'));

        static::expectException(UnableToRetrieveMetadata::class);
        $fs->mimeType('foo.txt');
    }

    public function testInvalidMimeType(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a', 'test');

        static::expectException(UnableToRetrieveMetadata::class);
        $fs->mimeType('a');
    }

    public function testLastModified(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a', 'test');

        static::assertEqualsWithDelta($fs->lastModified('a'), time(), 2);

        static::expectException(UnableToRetrieveMetadata::class);
        $fs->lastModified('ab');
    }

    public function testCopy(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());

        $fs->write('bla.txt', 'foo');
        $fs->copy('bla.txt', 'foo.txt');
        static::assertTrue($fs->fileExists('bla.txt'));
        static::assertTrue($fs->fileExists('foo.txt'));

        static::expectException(UnableToCopyFile::class);
        $fs->copy('blaa', 'blaa.txt');
    }

    public function testListing(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $fs->write('a/b/c/d/e/f/bla.txt', 'foo');

        $files = $fs->listContents('', true)->toArray();
        static::assertCount(7, $files);

        /** @var DirectoryAttributes[] $firstLevel */
        $firstLevel = $fs->listContents('', false)->toArray();
        static::assertCount(1, $firstLevel);
        static::assertTrue($firstLevel[0]->isDir());
        static::assertSame('a', $firstLevel[0]->path());
    }
}
