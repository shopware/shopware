<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaLoadedSubscriber
{
    /**
     * Accessed via generic entity accessors so it can serve both fully hydrated `MediaEntity`
     * instances (`media.loaded`) and `PartialEntity` instances from partial loading
     * (`media.partial_loaded`), which do not expose the typed media getters/setters.
     *
     * @param EntityLoadedEvent<MediaEntity>|PartialEntityLoadedEvent $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            $mediaTypeRaw = $media->has('mediaTypeRaw') ? $media->get('mediaTypeRaw') : null;

            if ($mediaTypeRaw) {
                /** @phpstan-ignore shopware.unserializeUsage */
                $media->assign(['mediaType' => \unserialize($mediaTypeRaw)]);
            }

            if (($media->has('thumbnails') ? $media->get('thumbnails') : null) !== null) {
                continue;
            }

            $thumbnailsRo = $media->has('thumbnailsRo') ? $media->get('thumbnailsRo') : null;

            $thumbnails = match (true) {
                /** @phpstan-ignore shopware.unserializeUsage */
                $thumbnailsRo !== null => \unserialize($thumbnailsRo),
                default => new MediaThumbnailCollection(),
            };

            $media->assign(['thumbnails' => $thumbnails]);
        }
    }
}
