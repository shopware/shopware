<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemOperator;
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
