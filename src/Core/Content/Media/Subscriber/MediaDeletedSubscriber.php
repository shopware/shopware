<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Util\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Framework\ORM\Event\EntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaDeletedSubscriber implements EventSubscriberInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var PathnameStrategyInterface
     */
    private $strategy;

    public function __construct(FilesystemInterface $filesystem, PathnameStrategyInterface $strategy)
    {
        $this->filesystem = $filesystem;
        $this->strategy = $strategy;
    }

    public static function getSubscribedEvents()
    {
        return [
            'media.deleted' => 'mediaDeleted',
        ];
    }

    public function mediaDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $mediaId) {
            $this->deleteMediaFileForEntity($mediaId);
        }
    }

    private function deleteMediaFileForEntity(string $mediaId): void
    {
        $path = $this->strategy->encode($mediaId);
        $dir = dirname($path);
        foreach ($this->filesystem->listContents('media/' . $dir) as $file) {
            if (preg_match('/^' . $mediaId . '[(\..*)_]/', $file['basename'])) {
                $this->filesystem->delete($file['path']);
            }
        }
    }
}
