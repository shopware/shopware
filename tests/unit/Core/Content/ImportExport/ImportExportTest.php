<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(ImportExport::class)]
class ImportExportTest extends TestCase
{
    public function testImportWithFinishedProgress(): void
    {
        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::never())->method('append');

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::never())->method('in');
        $pipe->expects(static::never())->method('out');

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'file' => (new ImportExportFileEntity())->assign([
                'path' => 'foobar', 'size' => 1337,
            ]),
            'records' => 5,
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
            $pipe,
            $reader,
            $writer,
            $this->createMock(FileService::class),
        );

        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $logEntity->setState(Progress::STATE_SUCCEEDED);
        $importExport->import(Context::createDefaultContext());

        $logEntity->setState(Progress::STATE_ABORTED);
        $importExport->import(Context::createDefaultContext());

        $logEntity->setState(Progress::STATE_FAILED);
        $importExport->import(Context::createDefaultContext());
    }

    public function testImport(): void
    {
        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::once())->method('read')->willReturn([
            ['id' => 'id1', 'name' => 'foo'],
            ['id' => 'id2', 'name' => 'baz'],
            ['id' => 'id3', 'name' => 'bar'],
        ]);

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::never())->method('append');

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::never())->method('in');
        $pipe->expects(static::exactly(3))->method('out')->willReturnOnConsecutiveCalls([
            'id1' => ['id' => 'id1', 'name' => 'foo'],
        ], [
            'id2' => ['id' => 'id2', 'name' => 'baz'],
        ], [
            'id3' => ['id' => 'id3', 'name' => 'bar'],
        ]);

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'file' => (new ImportExportFileEntity())->assign([
                'originalName' => 'customer.csv',
                'expireDate' => new \DateTimeImmutable(),
                'path' => 'foobar',
                'size' => 1337,
            ]),
            'records' => 5,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $beforeImportRowEventCount = 0;
        $beforeImportRecordEventCount = 0;
        $afterImportRecordEventCount = 0;

        $eventDispatcher->expects(static::exactly(9))->method('dispatch')->willReturnCallback(function (Event $event) use (&$beforeImportRowEventCount, &$afterImportRecordEventCount, &$beforeImportRecordEventCount) {
            if ($event instanceof ImportExportBeforeImportRowEvent) {
                ++$beforeImportRowEventCount;
            }

            if ($event instanceof ImportExportAfterImportRecordEvent) {
                ++$afterImportRecordEventCount;
            }

            if ($event instanceof ImportExportBeforeImportRecordEvent) {
                ++$beforeImportRecordEventCount;
            }

            return $event;
        });
        $importExportService = $this->createMock(ImportExportService::class);
        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->createMock(FilesystemOperator::class),
            $eventDispatcher,
            $this->createMock(Connection::class),
            new StaticEntityRepository([], new CustomerDefinition()),
            $pipe,
            $reader,
            $writer,
            $this->createMock(FileService::class),
        );

        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $logEntity->setState(Progress::STATE_PROGRESS);
        $context = Context::createDefaultContext();

        static::assertFalse($context->hasState(Context::SKIP_TRIGGER_FLOW));
        $importExport->import($context);

        static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
        static::assertEquals(3, $beforeImportRowEventCount);
        static::assertEquals(3, $beforeImportRecordEventCount);
        static::assertEquals(3, $afterImportRecordEventCount);
    }
}
