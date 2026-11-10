<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InMemoryUtilFilesystem::class)]
class InMemoryUtilFilesystemTest extends TestCase
{
    /**
     * @param string|list<string> $path
     */
    #[DataProvider('existsCases')]
    #[TestDox('exists() reports $_dataName')]
    public function testExists(string|array $path, bool $expected): void
    {
        $filesystem = new InMemoryUtilFilesystem(['/app/src/Kernel.php' => '<?php']);
        $filesystem->mkdir('/app/var/cache/');

        static::assertSame($expected, $filesystem->exists($path));
    }

    /**
     * @return \Generator<string, array{string|list<string>, bool}>
     */
    public static function existsCases(): \Generator
    {
        yield 'a seeded file' => ['/app/src/Kernel.php', true];
        yield 'the directory of a seeded file' => ['/app/src', true];
        yield 'a directory spelled with a doubled separator and a trailing slash' => ['/app//src/', true];
        yield 'a directory created with mkdir' => ['/app/var/cache', true];
        yield 'the parent of a created directory' => ['/app/var', true];
        yield 'a prefix that is not a whole path segment' => ['/app/sr', false];
        yield 'a missing file' => ['/app/src/Missing.php', false];
        yield 'every path of an iterable' => [['/app/src', '/app/var/cache'], true];
        yield 'an iterable with one missing path' => [['/app/src', '/app/missing'], false];
    }

    #[TestDox('touch() creates an empty file and keeps existing content')]
    public function testTouch(): void
    {
        $filesystem = new InMemoryUtilFilesystem(['/app/a.txt' => 'content']);

        $filesystem->touch(['/app/a.txt', '/app/b.txt']);

        static::assertSame(['/app/a.txt' => 'content', '/app/b.txt' => ''], $filesystem->dumpedFiles());
    }

    #[TestDox('dumpFile() and appendToFile() write under the canonical path')]
    public function testDumpFileAndAppendToFile(): void
    {
        $filesystem = new InMemoryUtilFilesystem();

        $filesystem->dumpFile('/app//log.txt', 'one');
        $filesystem->appendToFile('/app/log.txt', 'two');
        $filesystem->appendToFile('/app/new.txt', 'three');

        static::assertSame(['/app/log.txt' => 'onetwo', '/app/new.txt' => 'three'], $filesystem->dumpedFiles());
        static::assertSame('onetwo', $filesystem->readFile('/app/./log.txt'));
    }

    #[DataProvider('unreadablePaths')]
    #[TestDox('readFile() throws for $_dataName')]
    public function testReadFileThrows(string $path): void
    {
        $filesystem = new InMemoryUtilFilesystem(['/app/src/Kernel.php' => '<?php']);

        $this->expectExceptionObject(new IOException(\sprintf('Failed to read file "%s": file does not exist.', $path)));

        $filesystem->readFile($path);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function unreadablePaths(): \Generator
    {
        yield 'a missing file' => ['/app/src/Missing.php'];
        yield 'a directory' => ['/app/src'];
    }

    #[TestDox('remove() drops the files and directories below each path and keeps their siblings')]
    public function testRemove(): void
    {
        $filesystem = new InMemoryUtilFilesystem([
            '/app/one/a.txt' => 'a',
            '/app/one/sub/b.txt' => 'b',
            '/app/one-sibling/c.txt' => 'c',
            '/app/two/d.txt' => 'd',
        ]);
        $filesystem->mkdir(['/app/one/empty', '/app/three']);

        $filesystem->remove(['/app/one/', '/app/three']);

        static::assertSame(['/app/one-sibling/c.txt' => 'c', '/app/two/d.txt' => 'd'], $filesystem->dumpedFiles());
        static::assertFalse($filesystem->exists('/app/one'));
        static::assertFalse($filesystem->exists('/app/three'));
        static::assertTrue($filesystem->exists('/app/one-sibling'));
    }

    /**
     * @param \Closure(InMemoryUtilFilesystem): mixed $call
     */
    #[DataProvider('unsupportedCalls')]
    #[TestDox('$_dataName is not supported')]
    public function testUnsupportedMethodsThrow(string $method, \Closure $call): void
    {
        $filesystem = new InMemoryUtilFilesystem(['/app/a.txt' => 'a']);

        $this->expectExceptionObject(new \LogicException(\sprintf('%s() is not supported by %s', $method, InMemoryUtilFilesystem::class)));

        $call($filesystem);
    }

    /**
     * @return \Generator<string, array{string, \Closure(InMemoryUtilFilesystem): mixed}>
     */
    public static function unsupportedCalls(): \Generator
    {
        yield 'copy()' => ['copy', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->copy('/app/a.txt', '/app/b.txt')];
        yield 'mirror()' => ['mirror', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->mirror('/app', '/copy')];
        yield 'rename()' => ['rename', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->rename('/app/a.txt', '/app/b.txt')];
        yield 'tempnam()' => ['tempnam', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->tempnam('/app', 'tmp')];
        yield 'chmod()' => ['chmod', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->chmod('/app/a.txt', 0o644)];
        yield 'chown()' => ['chown', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->chown('/app/a.txt', 'www-data')];
        yield 'chgrp()' => ['chgrp', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->chgrp('/app/a.txt', 'www-data')];
        yield 'symlink()' => ['symlink', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->symlink('/app', '/link')];
        yield 'hardlink()' => ['hardlink', static fn (InMemoryUtilFilesystem $filesystem) => $filesystem->hardlink('/app/a.txt', '/app/b.txt')];
    }

    #[TestDox('seeded paths are canonicalized')]
    public function testSeededPathsAreCanonicalized(): void
    {
        $filesystem = new InMemoryUtilFilesystem(['/app/../app/./a.txt' => 'a']);

        static::assertSame(['/app/a.txt' => 'a'], $filesystem->dumpedFiles());
    }
}
