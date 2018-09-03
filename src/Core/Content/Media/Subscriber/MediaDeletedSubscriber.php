<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
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

    /**
     * @var ThumbnailService
     */
    private $thumbnailService;

    public function __construct(FilesystemInterface $filesystem, PathnameStrategyInterface $strategy, ThumbnailService $thumbnailService)
    {
        $this->filesystem = $filesystem;
        $this->strategy = $strategy;
        $this->thumbnailService = $thumbnailService;
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
        $dir = \dirname($path);
        foreach ($this->filesystem->listContents('media/' . $dir) as $file) {
            if (preg_match('/^' . $mediaId . '(_.*)?(\..*)/', $file['basename'])) {
                $this->filesystem->delete($file['path']);
            }
        }
        $this->thumbnailService->deleteThumbnailFiles($mediaId);
    }
}
