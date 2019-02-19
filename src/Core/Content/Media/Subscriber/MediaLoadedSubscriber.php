<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaEvents;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Metadata\Type\NoMetadata;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MediaLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var MetadataLoader
     */
    private $metadataLoader;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        MetadataLoader $metadataLoader
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->metadataLoader = $metadataLoader;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaEvents::MEDIA_LOADED_EVENT => [
                ['unserialize', 10],
                ['addUrls'],
                ['loadTypedMetadata'],
            ],
        ];
    }

    public function addUrls(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
            if (!$media->hasFile()) {
                continue;
            }

            $media->setUrl($this->urlGenerator->getAbsoluteMediaUrl($media));

            foreach ($media->getThumbnails() as $thumbnail) {
                $this->addThumbnailUrl($thumbnail, $media);
            }
        }
    }

    public function loadTypedMetadata(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $metadata = $entity->getMetaData();

            if (!$metadata) {
                continue;
            }

            try {
                $this->metadataLoader->updateMetadata($metadata);
            } catch (\Throwable $e) {
                // don't fail the request because metadata cannot be loaded
                $metadata->setType(new NoMetadata());
            }
        }
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
            if ($media->getMetaDataRaw()) {
                $media->setMetaData(unserialize($media->getMetaDataRaw()));
            }

            if ($media->getMediaTypeRaw()) {
                $media->setMediaType(unserialize($media->getMediaTypeRaw()));
            }
        }
    }

    private function addThumbnailUrl(MediaThumbnailEntity $thumbnail, MediaEntity $media): void
    {
        $thumbnail->setUrl(
            $this->urlGenerator->getAbsoluteThumbnailUrl(
                $media,
                $thumbnail->getWidth(),
                $thumbnail->getHeight()
            )
        );
    }
}
