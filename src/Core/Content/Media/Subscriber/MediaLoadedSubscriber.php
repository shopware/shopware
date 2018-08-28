<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public static function getSubscribedEvents()
    {
        return [
            'media.loaded' => 'mediaLoaded',
        ];
    }

    public function mediaLoaded(EntityLoadedEvent $event): void
    {
        /** @var MediaStruct $media */
        foreach ($event->getEntities() as $media) {
            if ($media->getMimeType() === null) {
                continue;
            }

            $media->setUrl($this->urlGenerator->getMediaUrl($media->getId(), $media->getFileExtension()));

            foreach ($media->getThumbnails()->getElements() as $thumbnail) {
                $this->addThumbnailUrl($thumbnail, $media);
            }
        }
    }

    private function addThumbnailUrl(MediaThumbnailStruct $thumbnail, MediaStruct $media): void
    {
        $thumbnail->setUrl(
            $this->urlGenerator->getThumbnailUrl(
                $media->getId(),
                $media->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                $thumbnail->getHighDpi()
            )
        );
    }
}
