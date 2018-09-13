<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
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

    public static function getSubscribedEvents()
    {
        return [
            'media.loaded' => [
                ['addUrls'],
                ['loadTypedMetadata'],
            ],
        ];
    }

    public function addUrls(EntityLoadedEvent $event): void
    {
        /** @var MediaStruct $media */
        foreach ($event->getEntities() as $media) {
            if (!$media->getHasFile()) {
                continue;
            }

            $media->setUrl($this->urlGenerator->getAbsoluteMediaUrl($media->getId(), $media->getFileExtension()));

            foreach ($media->getThumbnails()->getElements() as $thumbnail) {
                $this->addThumbnailUrl($thumbnail, $media);
            }
        }
    }

    public function loadTypedMetadata(EntityLoadedEvent $event): void
    {
        /** @var MediaStruct $entity */
        foreach ($event->getEntities() as $entity) {
            $metadata = $entity->getMetaData();

            if (!$metadata) {
                continue;
            }

            $this->metadataLoader->updateMetadata($metadata);
        }
    }

    private function addThumbnailUrl(MediaThumbnailStruct $thumbnail, MediaStruct $media): void
    {
        $thumbnail->setUrl(
            $this->urlGenerator->getAbsoluteThumbnailUrl(
                $media->getId(),
                $media->getFileExtension(),
                $thumbnail->getWidth(),
                $thumbnail->getHeight(),
                $thumbnail->getHighDpi()
            )
        );
    }
}
