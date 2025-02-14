<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Message\ImportExportHandler;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportHandler::class)]
class ImportExportHandlerTest extends TestCase
{
    #[DataProvider('dataProviderForTestImportExport')]
    public function testImportExportHandlerDispatchesMessage(string $activity, string $method): void
    {
        $messageBus = new CollectingMessageBus();

        $factory = $this->createMock(ImportExportFactory::class);

        $eventDispatcher = new EventDispatcher();

        $adminSource = new AdminApiSource('userId');
        $adminSource->setIsAdmin(true);
        $context = Context::createDefaultContext($adminSource);

        $importExportHandler = new ImportExportHandler($messageBus, $factory, $eventDispatcher);

        $logEntity = new ImportExportLogEntity();
        $logEntity->setActivity($activity);
        $logEntity->setState(Progress::STATE_PROGRESS);
        $logEntity->setId('logId');

        $progress = new Progress($logEntity->getId(), $logEntity->getState());

        $importExport = $this->createMock(ImportExport::class);
        $importExport->method($method)
            ->willReturn($progress);
        $importExport->method('getLogEntity')
            ->willReturn($logEntity);

        $factory->method('create')
            ->willReturn($importExport);

        $importExportMessage = new ImportExportMessage($context, $logEntity->getId(), $logEntity->getActivity());

        $importExportHandler->__invoke($importExportMessage);

        $messages = $messageBus->getMessages();

        $importExportMessage = null;
        foreach ($messages as $message) {
            if ($message->getMessage() instanceof ImportExportMessage) {
                $importExportMessage = $message->getMessage();
            }
        }

        static::assertNotNull($importExportMessage);

        /** @var Context $readContext */
        $readContext = $importExportMessage->getContext();
        static::assertSame($context, $readContext);

        /** @var AdminApiSource $source */
        $source = $readContext->getSource();
        static::assertSame($adminSource, $source);
        static::assertTrue($source->isAdmin());

        static::assertSame($logEntity->getId(), $importExportMessage->getLogId());
        static::assertSame($logEntity->getActivity(), $importExportMessage->getActivity());
    }

    /**
     * @return iterable<string, array{activity: string}>
     */
    public static function dataProviderForTestImportExport(): iterable
    {
        yield 'Test import process' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
        ];

        yield 'Test export process' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
        ];

        yield 'Test dryrun import process' => [
            'activity' => ImportExportLogEntity::ACTIVITY_DRYRUN,
            'method' => 'import',
        ];
    }

    public function testImportExportHandlerUnknownActivity(): void
    {
        $messageBus = new CollectingMessageBus();

        $factory = $this->createMock(ImportExportFactory::class);

        $eventDispatcher = new EventDispatcher();

        $importExportExceptionImportExportHandlerEventCount = 0;

        $adminSource = new AdminApiSource('userId');
        $adminSource->setIsAdmin(true);
        $context = Context::createDefaultContext($adminSource);

        $importExportHandler = new ImportExportHandler($messageBus, $factory, $eventDispatcher);

        $logEntity = new ImportExportLogEntity();
        $logEntity->setActivity('unknown_activity');
        $logEntity->setState(Progress::STATE_PROGRESS);
        $logEntity->setId('logId');

        $progress = new Progress($logEntity->getId(), $logEntity->getState());

        $importExport = $this->createMock(ImportExport::class);
        $importExport->method('exportExceptions')
            ->willReturn($progress);
        $importExport->method('getLogEntity')
            ->willReturn($logEntity);

        $factory->method('create')
            ->willReturn($importExport);

        $importExportMessage = new ImportExportMessage($context, $logEntity->getId(), $logEntity->getActivity());

        $eventDispatcher->addListener(
            ImportExportExceptionImportExportHandlerEvent::class,
            function (ImportExportExceptionImportExportHandlerEvent $event) use (&$importExportExceptionImportExportHandlerEventCount, $importExportMessage): void {
                static::assertInstanceOf(ImportExportException::class, $event->getException());
                static::assertSame('The activity "unknown_activity" could not be processed.', $event->getException()->getMessage());
                static::assertSame($importExportMessage, $event->getMessage());
                ++$importExportExceptionImportExportHandlerEventCount;
            }
        );

        $importExportHandler->__invoke($importExportMessage);

        $messages = $messageBus->getMessages();

        $importExportMessage = null;
        foreach ($messages as $message) {
            if ($message->getMessage() instanceof ImportExportMessage) {
                $importExportMessage = $message->getMessage();
            }
        }

        static::assertNotNull($importExportMessage);

        $readContext = $importExportMessage->getContext();
        static::assertSame($context, $readContext);

        $source = $readContext->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        static::assertSame($adminSource, $source);
        static::assertTrue($source->isAdmin());

        static::assertSame($logEntity->getId(), $importExportMessage->getLogId());
        static::assertSame($logEntity->getActivity(), $importExportMessage->getActivity());

        static::assertSame(1, $importExportExceptionImportExportHandlerEventCount);
    }

    #[DataProvider('provideDataForTestingExportFinishedEventIsDispatched')]
    public function testImportExportHandlerDispatchesProcessFinishedEventWhenImportAndExportAreFinished(
        string $activity,
        string $method,
        string $processState,
        bool $expectEventShouldBeDispatched,
    ): void {
        $messageBus = new CollectingMessageBus();
        $factory = $this->createMock(ImportExportFactory::class);
        $importExport = $this->createMock(ImportExport::class);
        $context = Context::createDefaultContext();
        $eventDispatcher = new EventDispatcher();

        $logEntity = new ImportExportLogEntity();
        $logEntity->setId('test-id');
        $logEntity->setActivity($activity);
        $logEntity->setState($processState);

        $progress = new Progress($logEntity->getId(), $logEntity->getState());

        $factory->expects(static::once())
            ->method('create')
            ->willReturn($importExport);

        $importExport->expects(static::once())
            ->method('getLogEntity')
            ->willReturn($logEntity);

        if ($processState !== Progress::STATE_ABORTED) {
            $importExport->expects(static::once())
                ->method($method)
                ->willReturn($progress);
        }

        $importExportHandler = new ImportExportHandler($messageBus, $factory, $eventDispatcher);

        $dispatchedEvent = null;
        $eventDispatcher->addListener(
            ImportExportAfterProcessFinishedEvent::class,
            function (ImportExportAfterProcessFinishedEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            }
        );

        $message = new ImportExportMessage($context, 'test-id', ImportExportLogEntity::ACTIVITY_EXPORT);
        $importExportHandler->__invoke($message);

        if (!$expectEventShouldBeDispatched) {
            static::assertNull($dispatchedEvent, 'Event should not have been dispatched');
        } else {
            static::assertNotNull($dispatchedEvent, 'Event should have been dispatched');
            static::assertSame($logEntity, $dispatchedEvent->getLogEntity());
            static::assertSame($progress, $dispatchedEvent->getProgress());
            static::assertSame($context, $dispatchedEvent->getContext());
        }
    }

    /**
     * @return iterable<string, array{
     *     activity: string,method: string,processState: string,expectEventShouldBeDispatched: bool
     * }>
     */
    public static function provideDataForTestingExportFinishedEventIsDispatched(): iterable
    {
        yield 'test ImportExportAfterProcessFinishedEvent is dispatched when export process failed' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
            'processState' => Progress::STATE_FAILED,
            'expectEventShouldBeDispatched' => true,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent is dispatched when export process succeeded' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
            'processState' => Progress::STATE_SUCCEEDED,
            'expectEventShouldBeDispatched' => true,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when export is aborted' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
            'processState' => Progress::STATE_ABORTED,
            'expectEventShouldBeDispatched' => false,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when export is merging files' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
            'processState' => Progress::STATE_MERGING_FILES,
            'expectEventShouldBeDispatched' => false,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when export is in progress' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
            'method' => 'export',
            'processState' => Progress::STATE_PROGRESS,
            'expectEventShouldBeDispatched' => false,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent is dispatched when export import failed' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
            'processState' => Progress::STATE_FAILED,
            'expectEventShouldBeDispatched' => true,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent is dispatched when export import succeeded' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
            'processState' => Progress::STATE_SUCCEEDED,
            'expectEventShouldBeDispatched' => true,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when import is aborted' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
            'processState' => Progress::STATE_ABORTED,
            'expectEventShouldBeDispatched' => false,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when import is in progress' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
            'processState' => Progress::STATE_PROGRESS,
            'expectEventShouldBeDispatched' => false,
        ];

        yield 'test ImportExportAfterProcessFinishedEvent will not be dispatched when import is merging files' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
            'method' => 'import',
            'processState' => Progress::STATE_MERGING_FILES,
            'expectEventShouldBeDispatched' => false,
        ];
    }
}
