<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Message\ImportExportHandler;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(ImportExportHandler::class)]
class ImportExportHandlerTest extends TestCase
{
    #[DataProvider('dataProviderForTestImportExport')]
    public function testImportExportHandlerDispatchesMessage(string $activity): void
    {
        $messageBus = new CollectingMessageBus();

        $factory = $this->createMock(ImportExportFactory::class);

        $adminSource = new AdminApiSource('userId');
        $adminSource->setIsAdmin(true);
        $context = Context::createDefaultContext($adminSource);

        $importExportHandler = new ImportExportHandler($messageBus, $factory);

        $logEntity = new ImportExportLogEntity();
        $logEntity->setActivity($activity);
        $logEntity->setState(Progress::STATE_PROGRESS);
        $logEntity->setId('logId');

        $progress = new Progress($logEntity->getId(), $logEntity->getState());

        $importExport = $this->createMock(ImportExport::class);
        $importExport->method('import')
            ->willReturn($progress);
        $importExport->method('getLogEntity')
            ->willReturn($logEntity);

        $factory->method('create')
            ->willReturn($importExport);

        $importExportMessage = new ImportExportMessage($context, $logEntity->getId(), $logEntity->getActivity());

        $importExportHandler->__invoke($importExportMessage);

        $messages = $messageBus->getMessages();

        $importExportMessage = null;
        /** @var Envelope $message */
        foreach ($messages as $message) {
            if ($message->getMessage() instanceof ImportExportMessage) {
                $importExportMessage = $message->getMessage();
            }
        }

        static::assertNotNull($importExportMessage);

        /** @var Context $readContext */
        $readContext = $importExportMessage->getContext();
        static::assertEquals($context, $readContext);

        /** @var AdminApiSource $source */
        $source = $readContext->getSource();
        static::assertEquals($adminSource, $source);
        static::assertTrue($source->isAdmin());

        static::assertEquals($logEntity->getId(), $importExportMessage->getLogId());
        static::assertEquals($logEntity->getActivity(), $importExportMessage->getActivity());
    }

    /**
     * @return iterable<string, array{activity: string}>
     */
    public static function dataProviderForTestImportExport(): iterable
    {
        yield 'Test import process' => [
            'activity' => ImportExportLogEntity::ACTIVITY_IMPORT,
        ];

        yield 'Test export process' => [
            'activity' => ImportExportLogEntity::ACTIVITY_EXPORT,
        ];
    }
}
