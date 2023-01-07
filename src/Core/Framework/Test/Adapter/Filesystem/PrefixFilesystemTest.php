<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use League\Flysystem\Visibility;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;

/**
 * @internal
 */
class PrefixFilesystemTest extends TestCase
{
    public function testPrefix(): void
    {
        $generator = $this->createMock(TemporaryUrlGenerator::class);
        $generator->method('temporaryUrl')->willReturn('http://example.com/temporary-url');

        $fs = new Filesystem(new MemoryFilesystemAdapter(), ['public_url' => 'http://example.com'], null, null, $generator);
        $prefix = new PrefixFilesystem($fs, 'foo');

        $prefix->write('foo.txt', 'bla');
        static::assertTrue($prefix->fileExists('foo.txt'));
        static::assertTrue($prefix->has('foo.txt'));
        static::assertFalse($prefix->directoryExists('foo.txt'));
        static::assertTrue($fs->has('foo/foo.txt'));
        static::assertFalse($fs->directoryExists('foo/foo.txt'));

        static::assertSame('bla', $prefix->read('foo.txt'));
        static::assertSame('bla', stream_get_contents($prefix->readStream('foo.txt')));
        static::assertSame('text/plain', $prefix->mimeType('foo.txt'));
        static::assertSame(3, $prefix->fileSize('foo.txt'));
        static::assertSame(Visibility::PUBLIC, $prefix->visibility('foo.txt'));
        $prefix->setVisibility('foo.txt', Visibility::PRIVATE);
        static::assertSame(Visibility::PRIVATE, $prefix->visibility('foo.txt'));
        static::assertEqualsWithDelta($prefix->lastModified('foo.txt'), time(), 2);

        static::assertSame('http://example.com/foo/foo.txt', $prefix->publicUrl('foo.txt'));
        static::assertSame('128ecf542a35ac5270a87dc740918404', $prefix->checksum('foo.txt'));
        static::assertSame('http://example.com/temporary-url', $prefix->temporaryUrl('foo.txt', new \DateTime('+1 hour')));

        $prefix->copy('foo.txt', 'bla.txt');
        static::assertTrue($prefix->has('bla.txt'));

        $prefix->createDirectory('dir');
        static::assertTrue($prefix->directoryExists('dir'));
        static::assertFalse($prefix->directoryExists('dir2'));
        $prefix->deleteDirectory('dir');
        static::assertFalse($prefix->directoryExists('dir'));

        $prefix->move('bla.txt', 'bla2.txt');
        static::assertFalse($prefix->has('bla.txt'));
        static::assertTrue($prefix->has('bla2.txt'));

        $prefix->delete('bla2.txt');
        static::assertFalse($prefix->has('bla2.txt'));

        $prefix->createDirectory('test');

        $files = $prefix->listContents('', true)->toArray();
        static::assertCount(2, $files);
    }

    public function testWriteStream(): void
    {
        $fs = new Filesystem(new MemoryFilesystemAdapter());
        $prefix = new PrefixFilesystem($fs, 'foo');
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmpFile, 'test');

        $prefix->writeStream('a.txt', fopen($tmpFile, 'rb'));

        static::assertTrue($prefix->fileExists('a.txt'));
        static::assertSame('test', $prefix->read('a.txt'));
        static::assertSame('test', stream_get_contents($prefix->readStream('a.txt')));

        unlink($tmpFile);
    }

    public function testEmptyPrefix(): void
    {
        static::expectException(\InvalidArgumentException::class);
        new PrefixFilesystem(new Filesystem(new MemoryFilesystemAdapter()), '');
    }
}
