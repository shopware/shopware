<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandler;

/**
 * @internal
 */
#[CoversClass(ProductExportFileHandler::class)]
class ProductExportFileHandlerTest extends TestCase
{
    public function testWriteProductExportContentReplacesFilesViaTemporaryPath(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with(
                static::callback(static function (string $path): bool {
                    return str_starts_with($path, 'export/feed.csv.')
                        && str_ends_with($path, '.tmp');
                }),
                'new-content'
            );
        $filesystem->expects($this->once())
            ->method('move')
            ->with(
                static::callback(static function (string $path): bool {
                    return str_starts_with($path, 'export/feed.csv.')
                        && str_ends_with($path, '.tmp');
                }),
                'export/feed.csv',
                []
            );
        $filesystem->expects($this->never())->method('delete');

        $handler = new ProductExportFileHandler($filesystem, 'export');

        static::assertTrue($handler->writeProductExportContent('new-content', 'export/feed.csv'));
    }

    public function testWriteProductExportContentAppendsExistingContent(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->once())
            ->method('fileExists')
            ->with('export/feed.csv.partial')
            ->willReturn(true);
        $filesystem->expects($this->once())
            ->method('read')
            ->with('export/feed.csv.partial')
            ->willReturn('existing-');
        $filesystem->expects($this->once())
            ->method('write')
            ->with('export/feed.csv.partial', 'existing-next');
        $filesystem->expects($this->never())->method('move');

        $handler = new ProductExportFileHandler($filesystem, 'export');

        static::assertTrue($handler->writeProductExportContent('next', 'export/feed.csv.partial', true));
    }
}
