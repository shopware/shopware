<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaWrittenSubscriber implements EventSubscriberInterface
{
    /**
     * @var FileSaver
     */
    private $fileSaver;

    public function __construct(FileSaver $fileSaver)
    {
        $this->fileSaver = $fileSaver;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_WRITTEN_EVENT => 'changeVisibility',
        ];
    }

    public function changeVisibility(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (array_key_exists('hidden', $payload)) {
                if ($payload['hidden'] === true) {
                    $this->fileSaver->hideMediaFile($writeResult->getPrimaryKey(), $event->getContext());
                } else {
                    $this->fileSaver->revealMediaFile($writeResult->getPrimaryKey(), $event->getContext());
                }
            }
        }
    }
}
