<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Event\Subscriber;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEvents;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Message\DeleteFileMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class FileDeletedSubscriber implements EventSubscriberInterface
{
    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public static function getSubscribedEvents()
    {
        return [ImportExportFileEvents::IMPORT_EXPORT_FILE_DELETED_EVENT => 'onFileDeleted'];
    }

    public function onFileDeleted(EntityDeletedEvent $event): void
    {
        $paths = [];
        $activities = [
            ImportExportLogEntity::ACTIVITY_IMPORT,
            ImportExportLogEntity::ACTIVITY_DRYRUN,
            ImportExportLogEntity::ACTIVITY_EXPORT,
        ];
        foreach ($event->getIds() as $fileId) {
            $path = ImportExportFileEntity::buildPath($fileId);
            // since the file could be stored in any one directory of the available activities
            foreach ($activities as $activitiy) {
                $paths[] = $activitiy . '/' . $path;
                // if file is not of an export there might be a log of invalid records
                if ($activitiy !== ImportExportLogEntity::ACTIVITY_EXPORT) {
                    $paths[] = $activitiy . '/' . $path . '_invalid';
                }
            }
        }

        $message = new DeleteFileMessage();
        $message->setFiles($paths);

        $this->messageBus->dispatch($message);
    }
}
