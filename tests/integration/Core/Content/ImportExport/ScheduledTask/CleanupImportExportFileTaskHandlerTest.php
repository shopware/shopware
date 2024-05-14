<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\ScheduledTask;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Message\DeleteFileHandler;
use Shopware\Core\Content\ImportExport\Message\DeleteFileMessage;
use Shopware\Core\Content\ImportExport\ScheduledTask\CleanupImportExportFileTaskHandler;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Content\ImportExport\AbstractImportExportTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('services-settings')]
class CleanupImportExportFileTaskHandlerTest extends AbstractImportExportTestCase
{
    private EntityRepository $logRepository;

    private EntityRepository $fileRepository;

    private FilesystemOperator $filesystem;

    private MessageBusInterface $messageBus;

    private DeleteFileHandler $deleteFileHandler;

    protected function setUp(): void
    {
        $this->logRepository = $this->getContainer()->get('import_export_log.repository');
        $this->fileRepository = $this->getContainer()->get('import_export_file.repository');
        $this->filesystem = $this->getContainer()->get('shopware.filesystem.private');
        $this->messageBus = $this->getContainer()->get('messenger.bus.shopware');
        $this->deleteFileHandler = $this->getContainer()->get(DeleteFileHandler::class);

        parent::setUp();
    }

    public function testDeletesFilesAndLogs(): void
    {
        $progressA = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME);
        $progressB = $this->export(Context::createDefaultContext(), ProductDefinition::ENTITY_NAME);
        $logIdA = $progressA->getLogId();
        $logIdB = $progressB->getLogId();
        $fileIdA = $this->getLogEntity($logIdA)->getFile()?->getId();
        static::assertIsString($fileIdA);
        $fileIdB = $this->getLogEntity($logIdB)->getFile()?->getId();
        static::assertIsString($fileIdB);

        $this->fileRepository->update([
            [
                'id' => $fileIdB,
                'expireDate' => (new \DateTime())->modify('-31 days'),
            ],
        ], Context::createDefaultContext());

        $expiredFilePath = $this->getLogEntity($logIdB)->getFile()?->getPath();
        static::assertIsString($expiredFilePath);

        $handler = $this->getContainer()->get(CleanupImportExportFileTaskHandler::class);

        $handler->run();

        // Expired log and file entities should've been deleted, not yet expired should still exist
        static::assertTrue($this->logEntityExists($logIdA));
        static::assertTrue($this->fileEntityExists($fileIdA));
        static::assertFalse($this->logEntityExists($logIdB));
        static::assertFalse($this->fileEntityExists($fileIdB));

        // Actual file should get deleted from filesystem
        static::assertTrue($this->filesystem->fileExists($expiredFilePath));

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();
        $deleteFileMessage = null;
        foreach ($messages as $message) {
            if (isset($message['message']) && $message['message'] instanceof DeleteFileMessage) {
                $deleteFileMessage = $message['message'];
            }
        }
        static::assertNotNull($deleteFileMessage);

        $this->deleteFileHandler->__invoke($deleteFileMessage);
        static::assertFalse($this->filesystem->fileExists($expiredFilePath));
    }

    private function logEntityExists(string $id): bool
    {
        return $this->logRepository->searchIds(new Criteria([$id]), Context::createDefaultContext())->firstId() !== null;
    }

    private function fileEntityExists(string $id): bool
    {
        return $this->fileRepository->searchIds(new Criteria([$id]), Context::createDefaultContext())->firstId() !== null;
    }
}
