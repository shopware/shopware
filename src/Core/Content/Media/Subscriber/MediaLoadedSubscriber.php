<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaEvents;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('content')]
class MediaLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_LOADED_EVENT => [
                ['unserialize', 10],
                ['addUrls'],
            ],
        ];
    }

    public function addUrls(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
            if (!$media->hasFile() || $media->isPrivate()) {
                continue;
            }

            $media->setUrl($this->urlGenerator->getAbsoluteMediaUrl($media));

            foreach ($media->getThumbnails() ?? [] as $thumbnail) {
                $this->addThumbnailUrl($thumbnail, $media);
            }
        }
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
            if ($media->getMediaTypeRaw()) {
                $media->setMediaType(unserialize($media->getMediaTypeRaw()));
            }

            if ($media->getThumbnails() === null) {
                if ($media->getThumbnailsRo()) {
                    $media->setThumbnails(unserialize($media->getThumbnailsRo()));
                } else {
                    $media->setThumbnails(new MediaThumbnailCollection());
                }
            }
        }
    }

    private function addThumbnailUrl(MediaThumbnailEntity $thumbnail, MediaEntity $media): void
    {
        $thumbnail->setUrl(
            $this->urlGenerator->getAbsoluteThumbnailUrl(
                $media,
                $thumbnail
            )
        );
    }
}
