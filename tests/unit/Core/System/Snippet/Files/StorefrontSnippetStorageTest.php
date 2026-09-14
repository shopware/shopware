<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Files;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem as AppFilesystem;
use Shopware\Core\System\Snippet\Files\StorefrontSnippetStorage;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as Io;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontSnippetStorage::class)]
class StorefrontSnippetStorageTest extends TestCase
{
    private SnippetSnapshotAdapter $adapter;

    private Filesystem $filesystem;

    private Io $io;

    /**
     * @var non-empty-string
     */
    private string $directory;

    protected function setUp(): void
    {
        $this->adapter = new SnippetSnapshotAdapter();
        $this->filesystem = new Filesystem($this->adapter);
        $this->io = new Io();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('shopware-snippet-storage-', true);
    }

    protected function tearDown(): void
    {
        $this->io->remove($this->directory);
    }

    public function testPersistPublishesOneSnapshotWithPathsAndContents(): void
    {
        $storage = $this->createStorage();

        static::assertTrue($storage->persist('TestApp', '1.0.0', $this->source()));

        static::assertSame(1, $this->adapter->writes);
        static::assertJsonStringEqualsJsonString(
            '{"version":"1.0.0","files":{"Resources/snippet/storefront.de-DE.json":"{\"app\":{\"title\":\"Hallo\"}}","Resources/snippet/storefront.en-GB.base.json":"{\"app\":{\"title\":\"Hello\"}}"}}',
            $this->filesystem->read('translation/apps/TestApp.json')
        );
    }

    public function testSharedHitReadsOnceAndSubsequentRequestsUseOnlyLocalFiles(): void
    {
        $this->createStorage()->persist('TestApp', '1.0.0', $this->source());
        $reads = $this->adapter->reads;
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->never())->method('filesystemForAppName');

        $directory = $this->createStorage($source)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertSame($reads + 1, $this->adapter->reads);
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame('{"app":{"title":"Hello"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.en-GB.base.json'));

        $remote = $this->createMock(FilesystemOperator::class);
        $remote->expects($this->never())->method(static::anything());
        $storage = new StorefrontSnippetStorage($remote, $source, new NullLogger(), $this->directory);
        static::assertSame($directory, $storage->directory('TestApp', '1.0.0'));
        static::assertSame($reads + 1, $this->adapter->reads);
        static::assertSame(1, $this->adapter->writes);
    }

    #[DataProvider('backfillSources')]
    public function testSourceBackfillImmediatelyWarmsBothCaches(string $fixture, int $fileCount): void
    {
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->once())->method('filesystemForAppName')
            ->with('TestApp')->willReturn($this->source($fixture));

        $directory = $this->createStorage($source)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertStringStartsWith($this->directory, $directory);
        static::assertSame(1, $this->adapter->reads);
        static::assertSame(1, $this->adapter->writes);
        static::assertSame($directory, $this->createStorage($source)->directory('TestApp', '1.0.0'));
        static::assertSame(1, $this->adapter->reads);
        $snapshot = json_decode($this->filesystem->read('translation/apps/TestApp.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($snapshot);
        static::assertSame('1.0.0', $snapshot['version']);
        static::assertIsArray($snapshot['files']);
        static::assertCount($fileCount, $snapshot['files']);
    }

    public static function backfillSources(): \Generator
    {
        yield 'app with snippets' => ['AppWithStorefrontSnippets', 2];
        yield 'app without snippets' => ['AppWithoutSnippets', 0];
    }

    public function testMismatchedSharedVersionIsPreservedButSourceIsCachedLocally(): void
    {
        $this->createStorage()->persist('TestApp', '0.9.0', $this->source('AppWithoutSnippets'));
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->once())->method('filesystemForAppName')->willReturn($this->source());

        $directory = $this->createStorage($source)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame($directory, $this->createStorage($source)->directory('TestApp', '1.0.0'));
        static::assertSame(1, $this->adapter->writes);
        static::assertJsonStringEqualsJsonString('{"version":"0.9.0","files":[]}', $this->filesystem->read('translation/apps/TestApp.json'));
    }

    public function testSharedWriteFailureDoesNotPreventLocalReuse(): void
    {
        $this->adapter->failWrites = true;
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->once())->method('filesystemForAppName')->willReturn($this->source());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $directory = $this->createStorage($source, logger: $logger)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame($directory, $this->createStorage($source, logger: $logger)->directory('TestApp', '1.0.0'));
        static::assertSame(1, $this->adapter->reads);
        static::assertSame(1, $this->adapter->writes);
    }

    public function testSharedReadFailureFallsBackToSource(): void
    {
        $this->adapter->failReads = true;
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->once())->method('filesystemForAppName')->willReturn($this->source());

        $directory = $this->createStorage($source)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame($directory, $this->createStorage($source)->directory('TestApp', '1.0.0'));
        static::assertSame(1, $this->adapter->reads);
    }

    public function testUnavailableSourceIsLoggedAndSkipped(): void
    {
        $source = $this->createMock(SourceResolver::class);
        $source->expects($this->once())->method('filesystemForAppName')
            ->willThrowException(AppException::notFoundByField('TestApp', 'name'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        static::assertNull($this->createStorage($source, logger: $logger)->directory('TestApp', '1.0.0'));
        static::assertSame(0, $this->adapter->writes);
    }

    public function testInterruptedLocalPublicationPropagatesAndCanBeRetried(): void
    {
        $this->createStorage()->persist('TestApp', '1.0.0', $this->source());
        $io = $this->createMock(Io::class);
        $io->method('exists')->willReturn(false);
        $writes = 0;
        $io->expects($this->atLeastOnce())->method('dumpFile')->willReturnCallback(function (string $path, string $contents) use (&$writes): void {
            if (++$writes > 1) {
                throw new IOException('Simulated disk failure');
            }
            $this->io->dumpFile($path, $contents);
        });

        $this->expectException(IOException::class);

        try {
            $this->createStorage(io: $io)->directory('TestApp', '1.0.0');
        } finally {
            $directory = $this->createStorage()->directory('TestApp', '1.0.0');
            static::assertNotNull($directory);
            static::assertStringStartsWith($this->directory, $directory);
            static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
            static::assertSame('{"app":{"title":"Hello"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.en-GB.base.json'));
        }
    }

    public function testUpdateDuringReadKeepsBothLocalVersionsConsistent(): void
    {
        $storage = $this->createStorage();
        $storage->persist('TestApp', '1.0.0', $this->source());
        $this->adapter->afterRead = function () use ($storage): void {
            $storage->persist('TestApp', '2.0.0', $this->source('AppWithoutSnippets'));
            $newDirectory = $storage->directory('TestApp', '2.0.0');
            static::assertNotNull($newDirectory);
            static::assertFalse($this->io->exists($newDirectory . '/Resources/snippet'));
        };

        $oldDirectory = $storage->directory('TestApp', '1.0.0');

        static::assertNotNull($oldDirectory);
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($oldDirectory . '/Resources/snippet/storefront.de-DE.json'));
        $newDirectory = $storage->directory('TestApp', '2.0.0');
        static::assertNotNull($newDirectory);
        static::assertNotSame($oldDirectory, $newDirectory);
        static::assertFalse($this->io->exists($newDirectory . '/Resources/snippet'));
        static::assertSame($oldDirectory, $storage->directory('TestApp', '1.0.0'));
    }

    public function testConcurrentFillsOfTheSameVersionPublishCompleteContents(): void
    {
        $this->createStorage()->persist('TestApp', '1.0.0', $this->source());
        $io = $this->createMock(Io::class);
        $io->method('exists')->willReturn(false);
        $interleaved = false;
        $io->expects($this->atLeastOnce())->method('dumpFile')->willReturnCallback(function (string $path, string $contents) use (&$interleaved): void {
            $this->io->dumpFile($path, $contents);
            if ($interleaved) {
                return;
            }

            $interleaved = true;
            $directory = $this->createStorage()->directory('TestApp', '1.0.0');
            static::assertNotNull($directory);
            static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
            static::assertSame('{"app":{"title":"Hello"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.en-GB.base.json'));
        });

        $directory = $this->createStorage(io: $io)->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertSame($directory, $this->createStorage()->directory('TestApp', '1.0.0'));
        static::assertSame('{"app":{"title":"Hallo"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame('{"app":{"title":"Hello"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.en-GB.base.json'));
    }

    public function testNestedFilesKeepTheirPaths(): void
    {
        $storage = $this->createStorage();
        $storage->persist('TestApp', '1.0.0', $this->source('AppWithNestedSnippets'));

        $directory = $storage->directory('TestApp', '1.0.0');

        static::assertNotNull($directory);
        static::assertJsonStringEqualsJsonString('{"app":{"title":"Root"}}', $this->io->readFile($directory . '/Resources/snippet/storefront.de-DE.json'));
        static::assertSame(
            $this->io->readFile(__DIR__ . '/_fixtures/AppWithNestedSnippets/Resources/snippet/de_DE/storefront.de-DE.json'),
            $this->io->readFile($directory . '/Resources/snippet/de_DE/storefront.de-DE.json')
        );
    }

    public function testUnchangedContentsDoNotRequireInvalidationButRemovedFilesDo(): void
    {
        $storage = $this->createStorage();

        static::assertTrue($storage->persist('TestApp', '1.0.0', $this->source()));
        static::assertFalse($storage->persist('TestApp', '2.0.0', $this->source()));
        static::assertTrue($storage->persist('TestApp', '3.0.0', $this->source('AppWithoutSnippets')));
        static::assertJsonStringEqualsJsonString('{"version":"3.0.0","files":[]}', $this->filesystem->read('translation/apps/TestApp.json'));
    }

    public function testFailedLifecyclePublicationPropagatesAndLeavesPreviousSnapshot(): void
    {
        $storage = $this->createStorage();
        $storage->persist('TestApp', '1.0.0', $this->source());
        $previous = $this->filesystem->read('translation/apps/TestApp.json');
        $this->adapter->failWrites = true;

        $this->expectExceptionObject(UnableToWriteFile::atLocation('translation/apps/TestApp.json'));

        try {
            $storage->persist('TestApp', '2.0.0', $this->source('AppWithoutSnippets'));
        } finally {
            static::assertSame($previous, $this->filesystem->read('translation/apps/TestApp.json'));
        }
    }

    public function testRemoveOnlyDeletesTheRequestedAppSnapshot(): void
    {
        $storage = $this->createStorage();
        static::assertFalse($storage->remove('TestApp'));
        $storage->persist('TestApp', '1.0.0', $this->source());
        $storage->persist('OtherApp', '1.0.0', $this->source());

        static::assertTrue($storage->remove('TestApp'));
        static::assertFalse($this->filesystem->fileExists('translation/apps/TestApp.json'));
        static::assertTrue($this->filesystem->fileExists('translation/apps/OtherApp.json'));
    }

    private function createStorage(?SourceResolver $source = null, ?Io $io = null, ?LoggerInterface $logger = null): StorefrontSnippetStorage
    {
        return new StorefrontSnippetStorage(
            $this->filesystem,
            $source ?? static::createStub(SourceResolver::class),
            $logger ?? new NullLogger(),
            $this->directory,
            $io ?? $this->io
        );
    }

    private function source(string $fixture = 'AppWithStorefrontSnippets'): AppFilesystem
    {
        return new AppFilesystem(__DIR__ . '/_fixtures/' . $fixture);
    }
}

/**
 * @internal
 */
class SnippetSnapshotAdapter extends InMemoryFilesystemAdapter
{
    public int $reads = 0;

    public int $writes = 0;

    public bool $failReads = false;

    public bool $failWrites = false;

    public ?\Closure $afterRead = null;

    public function read(string $path): string
    {
        ++$this->reads;
        if ($this->failReads) {
            throw UnableToReadFile::fromLocation($path);
        }
        $contents = parent::read($path);
        $callback = $this->afterRead;
        $this->afterRead = null;
        $callback?->__invoke();

        return $contents;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        ++$this->writes;
        if ($this->failWrites) {
            throw UnableToWriteFile::atLocation($path);
        }

        parent::write($path, $contents, $config);
    }
}
