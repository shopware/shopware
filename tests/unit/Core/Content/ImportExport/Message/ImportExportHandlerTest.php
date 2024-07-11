<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
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
#[Package('services-settings')]
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
}
