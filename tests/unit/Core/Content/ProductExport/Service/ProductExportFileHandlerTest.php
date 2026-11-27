<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandler;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductExportFileHandler::class)]
class ProductExportFileHandlerTest extends TestCase
{
    private const NOW = '2026-07-21 12:00:00';

    public function testIsValidFileReturnsFalseWhenFileDoesNotExist(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: false);

        static::assertFalse(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(), $this->createExport(generateByCronjob: false))
        );
    }

    public function testForceRegeneratesEvenForCronjobManagedExport(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: true);

        // File exists, generated just now, cronjob managed: without force this would be valid (skipped),
        // but `--force` (ignoreCache) must always regenerate.
        $export = $this->createExport(generateByCronjob: true, generatedAt: self::NOW, interval: 3600);

        static::assertFalse(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(ignoreCache: true), $export)
        );
    }

    public function testForceRegeneratesForNonCronjobExport(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: true);

        $export = $this->createExport(generateByCronjob: false, generatedAt: self::NOW, interval: 3600);

        static::assertFalse(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(ignoreCache: true), $export)
        );
    }

    public function testCronjobManagedExportIsValidWithoutForce(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: true);

        $export = $this->createExport(generateByCronjob: true, generatedAt: null, interval: 3600);

        static::assertTrue(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(), $export)
        );
    }

    public function testNonCronjobExportIsValidWhileCacheIsFresh(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: true);

        // Generated now with a one hour interval, so the cache is still fresh.
        $export = $this->createExport(generateByCronjob: false, generatedAt: self::NOW, interval: 3600);

        static::assertTrue(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(), $export)
        );
    }

    public function testNonCronjobExportIsInvalidWhenCacheExpired(): void
    {
        $fileHandler = $this->createFileHandler(fileExists: true);

        // Generated two hours ago with a one hour interval, so the cache is expired.
        $export = $this->createExport(generateByCronjob: false, generatedAt: '2026-07-21 10:00:00', interval: 3600);

        static::assertFalse(
            $fileHandler->isValidFile('/export/feed.csv', new ExportBehavior(), $export)
        );
    }

    public function testWriteDoesNotRemoveExistingFeedBeforeNewContentIsInPlace(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('export/feed.xml', 'old');
        $fileSystem = new FeedWatchingFilesystem($inner, 'export/feed.xml');

        $handler = new ProductExportFileHandler($fileSystem, 'export', new FrozenClock());
        $handler->writeProductExportContent('new', 'export/feed.xml');

        static::assertSame([], $fileSystem->missingAfter);
        static::assertSame('new', $inner->read('export/feed.xml'));
        static::assertSame(['export/feed.xml'], $this->listFiles($inner));
    }

    public function testFinalizeDoesNotRemoveExistingFeedBeforeNewContentIsInPlace(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('export/feed.xml', 'old');
        $inner->write('export/feed.xml.partial', 'body');
        $fileSystem = new FeedWatchingFilesystem($inner, 'export/feed.xml');

        $handler = new ProductExportFileHandler($fileSystem, 'export', new FrozenClock());
        $handler->finalizePartialProductExport('export/feed.xml.partial', 'export/feed.xml', '<h>', '</h>');

        static::assertSame([], $fileSystem->missingAfter);
        static::assertSame('<h>body</h>', $inner->read('export/feed.xml'));
        static::assertSame(['export/feed.xml'], $this->listFiles($inner));
    }

    public function testFailedMoveRemovesTemporaryFileAndKeepsExistingFeed(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('export/feed.xml', 'old');
        $fileSystem = new FeedWatchingFilesystem($inner, 'export/feed.xml', failOn: 'move');

        $handler = new ProductExportFileHandler($fileSystem, 'export', new FrozenClock());

        $this->expectException(UnableToMoveFile::class);

        try {
            $handler->writeProductExportContent('new', 'export/feed.xml');
        } finally {
            static::assertSame('old', $inner->read('export/feed.xml'));
            static::assertSame(['export/feed.xml'], $this->listFiles($inner));
        }
    }

    public function testFailedWriteRemovesTemporaryFileAndKeepsExistingFeed(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('export/feed.xml', 'old');
        $inner->write('export/feed.xml.partial', 'body');
        $fileSystem = new FeedWatchingFilesystem($inner, 'export/feed.xml', failOn: 'write');

        $handler = new ProductExportFileHandler($fileSystem, 'export', new FrozenClock());

        $this->expectException(UnableToWriteFile::class);

        try {
            $handler->finalizePartialProductExport('export/feed.xml.partial', 'export/feed.xml', '<h>', '</h>');
        } finally {
            static::assertSame('old', $inner->read('export/feed.xml'));
            static::assertSame(['export/feed.xml'], $this->listFiles($inner));
        }
    }

    /**
     * @return list<string>
     */
    private function listFiles(FilesystemOperator $fileSystem): array
    {
        return array_values(array_map(
            static fn ($item) => $item->path(),
            $fileSystem->listContents('export', true)->toArray()
        ));
    }

    private function createFileHandler(bool $fileExists): ProductExportFileHandler
    {
        $fileSystem = static::createStub(FilesystemOperator::class);
        $fileSystem->method('fileExists')->willReturn($fileExists);

        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new ProductExportFileHandler($fileSystem, 'export', $clock);
    }

    private function createExport(
        bool $generateByCronjob,
        ?string $generatedAt = null,
        int $interval = 3600
    ): ProductExportEntity {
        $export = new ProductExportEntity();
        $export->setId('018f0c1e1e7b7c1e8e6b1a2b3c4d5e6f');
        $export->setFileName('feed.csv');
        $export->setGenerateByCronjob($generateByCronjob);
        $export->setInterval($interval);

        if ($generatedAt !== null) {
            $export->setGeneratedAt(new \DateTimeImmutable($generatedAt));
        }

        return $export;
    }
}

/**
 * @internal
 */
class FrozenClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-21 12:00:00');
    }
}

/**
 * @internal
 */
class FeedWatchingFilesystem implements FilesystemOperator
{
    /**
     * @var list<string>
     */
    public array $missingAfter = [];

    public function __construct(
        private readonly FilesystemOperator $inner,
        private readonly string $feed,
        private readonly ?string $failOn = null,
    ) {
    }

    public function fileExists(string $location): bool
    {
        return $this->inner->fileExists($location);
    }

    public function directoryExists(string $location): bool
    {
        return $this->inner->directoryExists($location);
    }

    public function has(string $location): bool
    {
        return $this->inner->has($location);
    }

    public function read(string $location): string
    {
        return $this->inner->read($location);
    }

    public function readStream(string $location)
    {
        return $this->inner->readStream($location);
    }

    public function listContents(string $location, bool $deep = self::LIST_SHALLOW): DirectoryListing
    {
        return $this->inner->listContents($location, $deep);
    }

    public function lastModified(string $path): int
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): int
    {
        return $this->inner->fileSize($path);
    }

    public function mimeType(string $path): string
    {
        return $this->inner->mimeType($path);
    }

    public function visibility(string $path): string
    {
        return $this->inner->visibility($path);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function write(string $location, string $contents, array $config = []): void
    {
        $this->inner->write($location, $contents, $config);
        if ($this->failOn === 'write') {
            throw UnableToWriteFile::atLocation($location, 'simulated failure');
        }
        $this->check('write ' . $location);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function writeStream(string $location, $contents, array $config = []): void
    {
        $this->inner->writeStream($location, $contents, $config);
        $this->check('writeStream ' . $location);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function delete(string $location): void
    {
        $this->inner->delete($location);
        $this->check('delete ' . $location);
    }

    public function deleteDirectory(string $location): void
    {
        $this->inner->deleteDirectory($location);
        $this->check('deleteDirectory ' . $location);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createDirectory(string $location, array $config = []): void
    {
        $this->inner->createDirectory($location, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        if ($this->failOn === 'move') {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
        $this->inner->move($source, $destination, $config);
        $this->check('move ' . $source . ' -> ' . $destination);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->inner->copy($source, $destination, $config);
        $this->check('copy ' . $source . ' -> ' . $destination);
    }

    private function check(string $operation): void
    {
        if (!$this->inner->fileExists($this->feed)) {
            $this->missingAfter[] = $operation;
        }
    }
}
