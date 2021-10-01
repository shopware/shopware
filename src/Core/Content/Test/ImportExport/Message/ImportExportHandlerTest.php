<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Message;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Message\ImportExportHandler;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\ImportExport\Service\ImportExportService;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Test\ImportExport\ImportExportTestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportExportHandlerTest extends ImportExportTestCase
{
    public function testImportExportHandlerDispatchesMessage(): void
    {
        $messageBus = $this->getContainer()->get('messenger.bus.shopware');
        $factory = $this->getContainer()->get(ImportExportFactory::class);
        $context = Context::createDefaultContext();

        $importExportHandler = new ImportExportHandler($messageBus, $factory);

        $importExportService = $this->getContainer()->get(ImportExportService::class);

        $profileId = $this->getDefaultProfileId(PropertyGroupOptionDefinition::ENTITY_NAME);

        $expireDate = new \DateTimeImmutable('2099-01-01');
        $file = new UploadedFile(__DIR__ . '/../fixtures/properties.csv', 'properties.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $profileId,
            $expireDate,
            $file
        );

        $importExportMessage = new ImportExportMessage($context, $logEntity->getId(), ImportExportLogEntity::ACTIVITY_IMPORT);
        $importExportHandler->handle($importExportMessage);

        $messages = $messageBus->getDispatchedMessages();

        $importExportMessage = null;
        foreach ($messages as $message) {
            if (isset($message['message']) && $message['message'] instanceof ImportExportMessage) {
                $importExportMessage = $message['message'];
            }
        }

        static::assertNotNull($importExportMessage);
        static::assertEquals($logEntity->getId(), $importExportMessage->getLogId());
        static::assertEquals($logEntity->getActivity(), $importExportMessage->getActivity());

        $updatedLogEntity = $this->getLogEntity($logEntity->getId());
        static::assertEquals(50, $updatedLogEntity->getRecords());

        $importExportHandler->handle($importExportMessage);
        $updatedLogEntity = $this->getLogEntity($logEntity->getId());
        static::assertEquals(100, $updatedLogEntity->getRecords());
    }
}
