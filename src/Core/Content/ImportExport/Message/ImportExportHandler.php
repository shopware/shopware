<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent;
use Shopware\Core\Content\ImportExport\ImportExport;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('services-settings')]
final class ImportExportHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ImportExportFactory $importExportFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(ImportExportMessage $message): void
    {
        $context = $message->getContext();

        $importExport = null;
        $logEntity = null;
        $progress = null;

        try {
            $importExport = $this->importExportFactory->create($message->getLogId(), 50, 50);
            $logEntity = $importExport->getLogEntity();

            if ($logEntity->getState() === Progress::STATE_ABORTED) {
                return;
            }

            if (
                $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_IMPORT
                || $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_DRYRUN
            ) {
                $progress = $importExport->import($context, $message->getOffset());
            } elseif ($logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT) {
                $progress = $importExport->export($context, new Criteria(), $message->getOffset());
            } else {
                throw ImportExportException::unknownActivity($logEntity->getActivity());
            }
        } catch (\Throwable $exception) {
            $event = new ImportExportExceptionImportExportHandlerEvent($exception, $message);
            $this->eventDispatcher->dispatch($event);

            $exception = $event->getException();

            if ($exception && $importExport instanceof ImportExport) {
                $progress = $importExport->exportExceptions($context, [['_error' => mb_convert_encoding($exception->getMessage(), 'UTF-8', 'UTF-8')]]);
            }
        }

        if ($logEntity instanceof ImportExportLogEntity && $progress instanceof Progress && !$progress->isFinished()) {
            $nextMessage = new ImportExportMessage(
                $context,
                $logEntity->getId(),
                $logEntity->getActivity(),
                $progress->getOffset()
            );

            $this->messageBus->dispatch($nextMessage);
        }
    }
}
