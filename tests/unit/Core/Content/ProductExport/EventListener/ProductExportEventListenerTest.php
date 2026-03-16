<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\EventListener;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\EventListener\ProductExportEventListener;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Service\ProductExportFileHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProductExportEventListener::class)]
class ProductExportEventListenerTest extends TestCase
{
    public function testAfterWriteResetsFlagsAndDeletesFileOnEdit(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $entity = new ProductExportEntity();
        $entity->setId($id);
        $entity->setUniqueIdentifier($id);
        $entity->setFileName('UnitTest.csv');

        $productExportRepository = $this->createMock(EntityRepository::class);
        $productExportRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $payload) use ($id): bool {
                $first = $payload[0] ?? [];

                return ($first['id'] ?? null) === $id
                    && \array_key_exists('generatedAt', $first)
                    && $first['generatedAt'] === null
                    && \array_key_exists('isRunning', $first)
                    && $first['isRunning'] === false;
            }), $context);

        $productExportRepository
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'product_export',
                1,
                new ProductExportCollection([$entity]),
                null,
                new Criteria([$id]),
                $context
            ));

        $fileHandler = $this->createMock(ProductExportFileHandlerInterface::class);
        $fileHandler
            ->expects($this->once())
            ->method('getFilePath')
            ->with(static::isInstanceOf(ProductExportEntity::class))
            ->willReturn('/export/UnitTest.csv');

        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects($this->once())->method('fileExists')->with('/export/UnitTest.csv')->willReturn(true);
        $fs->expects($this->once())->method('delete')->with('/export/UnitTest.csv');

        $listener = new ProductExportEventListener($productExportRepository, $fileHandler, $fs);

        $writeResult = new EntityWriteResult(['id' => $id], ['interval' => 300], 'product_export', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('product_export', [$writeResult], $context);

        $listener->afterWrite($event);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('skipPayloadProvider')]
    public function testAfterWriteSkipsWhenPayloadContainsManagedFields(array $payload): void
    {
        $context = Context::createDefaultContext();

        $productExportRepository = $this->createMock(EntityRepository::class);
        $productExportRepository->expects($this->never())->method('update');
        $productExportRepository->expects($this->never())->method('search');

        $fileHandler = $this->createMock(ProductExportFileHandlerInterface::class);
        $fileHandler->expects($this->never())->method('getFilePath');

        $fs = $this->createMock(FilesystemOperator::class);
        $fs->expects($this->never())->method('fileExists');
        $fs->expects($this->never())->method('delete');

        $listener = new ProductExportEventListener($productExportRepository, $fileHandler, $fs);

        $writeResult = new EntityWriteResult(['id' => Uuid::randomHex()], $payload, 'product_export', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('product_export', [$writeResult], $context);

        $listener->afterWrite($event);
    }

    public static function skipPayloadProvider(): \Generator
    {
        yield 'explicit generatedAt update' => [[
            'generatedAt' => new \DateTime(),
        ]];

        yield 'explicit isRunning update' => [[
            'isRunning' => true,
        ]];
    }
}
