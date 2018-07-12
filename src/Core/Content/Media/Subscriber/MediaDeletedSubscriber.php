<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Shopware\Core\Framework\ORM\Event\EntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaDeletedSubscriber implements EventSubscriberInterface
{
    /** @var FilesystemInterface */
    private $filesystem;

    /** @var StrategyInterface */
    private $strategy;

    /**
     * @param FilesystemInterface $filesystem
     * @param StrategyInterface   $strategy
     */
    public function __construct(FilesystemInterface $filesystem, StrategyInterface $strategy)
    {
        $this->filesystem = $filesystem;
        $this->strategy = $strategy;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'media.deleted' => 'mediaDeleted',
        ];
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function mediaDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $mediaId) {
            $this->deleteMediaFileForEntity($mediaId);
        }
    }

    /**
     * Deletes the Media File and depending Thumbnails of the Media Entity
     *
     * @param string $mediaId
     */
    private function deleteMediaFileForEntity($mediaId): void
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
