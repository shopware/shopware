<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Message;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Exception\ProcessingException;
use Shopware\Core\Content\ImportExport\ImportExportFactory;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('system-settings')]
final class ImportExportHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ImportExportFactory $importExportFactory
    ) {
    }

    public function __invoke(ImportExportMessage $message): void
    {
        $context = $message->getContext();
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
            throw new ProcessingException('Unknown activity');
        }

        if (!$progress->isFinished()) {
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
