<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionExportRecordEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('services-settings')]
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
            new EventDispatcher(),
            $this->createMock(Connection::class),
            new StaticEntityRepository([], new OrderDefinition()),
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

        $importExportBeforeImportRowEventCount = 0;
        $importExportAfterImportRecordEventCount = 0;
        $importExportBeforeImportRecordEventCount = 0;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            ImportExportBeforeImportRowEvent::class,
            function () use (&$importExportBeforeImportRowEventCount): void {
                ++$importExportBeforeImportRowEventCount;
            }
        );
        $eventDispatcher->addListener(
            ImportExportAfterImportRecordEvent::class,
            function () use (&$importExportAfterImportRecordEventCount): void {
                ++$importExportAfterImportRecordEventCount;
            }
        );
        $eventDispatcher->addListener(
            ImportExportBeforeImportRecordEvent::class,
            function () use (&$importExportBeforeImportRecordEventCount): void {
                ++$importExportBeforeImportRecordEventCount;
            }
        );

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
        static::assertSame(3, $importExportBeforeImportRowEventCount);
        static::assertSame(3, $importExportBeforeImportRecordEventCount);
        static::assertSame(3, $importExportAfterImportRecordEventCount);
    }

    public function testExportWithFinishedProgress(): void
    {
        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $eventDispatcher = new EventDispatcher();

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::never())->method('in');
        $pipe->expects(static::never())->method('out');

        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::never())->method('append');

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

        $context = Context::createDefaultContext();

        $logEntity->setState(Progress::STATE_SUCCEEDED);
        static::assertEquals(new Progress($logEntity->getId(), $logEntity->getState()), $importExport->export($context, new Criteria(), 0));

        $logEntity->setState(Progress::STATE_FAILED);
        static::assertEquals(new Progress($logEntity->getId(), $logEntity->getState()), $importExport->export($context, new Criteria(), 0));

        $logEntity->setState(Progress::STATE_ABORTED);
        static::assertEquals(new Progress($logEntity->getId(), $logEntity->getState()), $importExport->export($context, new Criteria(), 0));
    }

    public function testSuccessfulExport(): void
    {
        $exportFileName = 'order_export.csv';

        $fileId = Uuid::randomHex();

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'state' => Progress::STATE_PROGRESS,
            'fileId' => $fileId,
            'file' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'path' => 'tests/unit/Core/Content/ImportExport/fixtures/' . $exportFileName,
            ]),
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $enrichExportCriteriaEventCount = 0;
        $importExportBeforeExportRecordEventCount = 0;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            EnrichExportCriteriaEvent::class,
            function () use (&$enrichExportCriteriaEventCount): void {
                ++$enrichExportCriteriaEventCount;
            }
        );
        $eventDispatcher->addListener(
            ImportExportBeforeExportRecordEvent::class,
            function () use (&$importExportBeforeExportRecordEventCount): void {
                ++$importExportBeforeExportRecordEventCount;
            }
        );

        $orderId = Uuid::randomHex();

        $repository = new StaticEntityRepository(
            [new EntitySearchResult(
                OrderEntity::class,
                1,
                new EntityCollection([(new OrderEntity())->assign(['id' => $orderId])]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )],
            new OrderDefinition()
        );

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::exactly(1))->method('in')->willReturnCallback(
            function (Config $config, array $originalRecord): iterable {
                $serializedRecord = [];

                $serializedRecord['id'] = $originalRecord['id'];

                return $serializedRecord;
            }
        );
        $pipe->expects(static::never())->method('out');

        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::exactly(1))->method('append')->with(
            new Config([], [], []),
            [
                'id' => $orderId,
            ],
            0
        );
        $writer->expects(static::exactly(1))->method('flush');
        $writer->expects(static::exactly(1))->method('finish');

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->createMock(FilesystemOperator::class),
            $eventDispatcher,
            $this->createMock(Connection::class),
            $repository,
            $pipe,
            $reader,
            $writer,
            $this->createMock(FileService::class),
        );

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        static::assertEquals(
            (new Progress($logEntity->getId(), Progress::STATE_SUCCEEDED))->assign([
                'offset' => 1,
                'total' => 1,
                'processedRecords' => 1,
            ]),
            $importExport->export($context, $criteria, 0)
        );

        static::assertSame(1, $enrichExportCriteriaEventCount);
        static::assertSame(1, $importExportBeforeExportRecordEventCount);
    }

    public function testExportWithError(): void
    {
        $exportFileName = 'order_export.csv';
        $fileId = Uuid::randomHex();
        $profileId = Uuid::randomHex();
        $invalidRecordsLogId = Uuid::randomHex();

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'state' => Progress::STATE_PROGRESS,
            'fileId' => $fileId,
            'profileId' => $profileId,
            'file' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'path' => 'tests/unit/Core/Content/ImportExport/fixtures/' . $exportFileName,
                'originalName' => $exportFileName,
                'expireDate' => new \DateTimeImmutable(),
            ]),
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn (string $logId, int $offset) => new Progress($logId, Progress::STATE_PROGRESS)
            );
        $importExportService->expects(static::exactly(1))->method('prepareExport')
            ->willReturnCallback(
                fn () => (new ImportExportLogEntity())->assign([
                    'id' => $invalidRecordsLogId,
                    'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
                    'state' => Progress::STATE_PROGRESS,
                    'profileId' => $profileId,
                    'fileId' => $fileId,
                    'file' => (new ImportExportFileEntity())->assign([
                        'id' => $fileId,
                        'path' => 'tests/unit/Core/Content/ImportExport/fixtures/' . $exportFileName . '_invalid',
                    ]),
                ])
            );

        $enrichExportCriteriaEventCount = 0;
        $importExportBeforeExportRecordEventCount = 0;
        $importExportExceptionExportRecordEventCount = 0;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            EnrichExportCriteriaEvent::class,
            function () use (&$enrichExportCriteriaEventCount): void {
                ++$enrichExportCriteriaEventCount;
            }
        );
        $eventDispatcher->addListener(
            ImportExportBeforeExportRecordEvent::class,
            function () use (&$importExportBeforeExportRecordEventCount): void {
                ++$importExportBeforeExportRecordEventCount;
            }
        );
        $eventDispatcher->addListener(
            ImportExportExceptionExportRecordEvent::class,
            function () use (&$importExportExceptionExportRecordEventCount): void {
                ++$importExportExceptionExportRecordEventCount;
            }
        );

        $repository = new StaticEntityRepository(
            [new EntitySearchResult(
                OrderEntity::class,
                1,
                new EntityCollection([
                    (new OrderEntity())->assign(['id' => Uuid::randomHex(), 'language' => new LanguageEntity()]),
                ]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )],
            new OrderDefinition()
        );

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::exactly(2))->method('in')->willReturnCallback(
            function (Config $config, array $originalRecord): iterable {
                $serializedRecord = [];

                foreach ($originalRecord as $key => $value) {
                    $serializedRecord[$key] = $value;
                }

                $serializedRecord['extensions'] = '';
                $serializedRecord['translated'] = '';
                $serializedRecord['ruleIds'] = '';

                return $serializedRecord;
            }
        );
        $pipe->expects(static::never())->method('out');

        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::exactly(1))->method('append');
        $writer->expects(static::exactly(2))->method('flush');
        $writer->expects(static::exactly(1))->method('finish');

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->createMock(FilesystemOperator::class),
            $eventDispatcher,
            $this->createMock(Connection::class),
            $repository,
            $pipe,
            $reader,
            $writer,
            $this->createMock(FileService::class),
        );

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        static::assertEquals(
            (new Progress($logEntity->getId(), Progress::STATE_FAILED))->assign([
                'offset' => 1,
                'total' => 1,
                'invalidRecordsLogId' => $invalidRecordsLogId,
                'processedRecords' => 0,
            ]),
            $importExport->export($context, $criteria, 0)
        );

        static::assertSame(1, $enrichExportCriteriaEventCount);
        static::assertSame(1, $importExportBeforeExportRecordEventCount);
        static::assertSame(1, $importExportExceptionExportRecordEventCount);
    }

    public function testExportExceptions(): void
    {
        $exportFileName = 'order_export.csv';
        $fileId = Uuid::randomHex();
        $profileId = Uuid::randomHex();
        $invalidRecordsLogId = Uuid::randomHex();
        $errorMessage = 'Foo';

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'state' => Progress::STATE_PROGRESS,
            'fileId' => $fileId,
            'profileId' => $profileId,
            'file' => (new ImportExportFileEntity())->assign([
                'id' => $fileId,
                'path' => 'tests/unit/Core/Content/ImportExport/fixtures/' . $exportFileName,
                'originalName' => $exportFileName,
                'expireDate' => new \DateTimeImmutable(),
            ]),
        ]);

        $importExportService = $this->createMock(ImportExportService::class);
        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn (string $logId, int $offset) => new Progress($logId, Progress::STATE_PROGRESS)
            );
        $importExportService->expects(static::exactly(1))->method('prepareExport')
            ->willReturnCallback(
                fn () => (new ImportExportLogEntity())->assign([
                    'id' => $invalidRecordsLogId,
                    'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
                    'state' => Progress::STATE_PROGRESS,
                    'profileId' => $profileId,
                    'fileId' => $fileId,
                    'file' => (new ImportExportFileEntity())->assign([
                        'id' => $fileId,
                        'path' => 'tests/unit/Core/Content/ImportExport/fixtures/' . $exportFileName . '_invalid',
                    ]),
                ])
            );

        $importExportBeforeExportRecordEventCount = 0;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            ImportExportBeforeExportRecordEvent::class,
            function () use (&$importExportBeforeExportRecordEventCount): void {
                ++$importExportBeforeExportRecordEventCount;
            }
        );

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects(static::exactly(1))->method('in')->willReturnCallback(
            function (Config $config, iterable $originalRecord) use ($errorMessage): iterable {
                static::assertSame(['_error' => $errorMessage], $originalRecord);

                $serializedRecord = [];

                foreach ($originalRecord as $key => $value) {
                    $serializedRecord[$key] = $value;
                }

                return $serializedRecord;
            }
        );
        $pipe->expects(static::never())->method('out');

        $reader = $this->createMock(AbstractReader::class);
        $reader->expects(static::never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects(static::exactly(1))->method('append')->with(
            new Config([], [], []),
            ['_error' => $errorMessage],
            0
        );
        $writer->expects(static::exactly(1))->method('flush');
        $writer->expects(static::never())->method('finish');

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            $this->createMock(FilesystemOperator::class),
            $eventDispatcher,
            $this->createMock(Connection::class),
            new StaticEntityRepository([], new OrderDefinition()),
            $pipe,
            $reader,
            $writer,
            $this->createMock(FileService::class),
        );

        $context = Context::createDefaultContext();

        static::assertEquals(
            (new Progress($logEntity->getId(), Progress::STATE_FAILED))->assign([
                'offset' => 0,
                'total' => null,
                'invalidRecordsLogId' => $invalidRecordsLogId,
                'processedRecords' => 0,
            ]),
            $importExport->exportExceptions($context, [['_error' => $errorMessage]])
        );

        static::assertSame(1, $importExportBeforeExportRecordEventCount);
    }
}
