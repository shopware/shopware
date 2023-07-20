<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Service;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Path\Implementation\MediaPathUpdater;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * @final This class is not intended to extend, but the event listener calls can be removed via compiler pass
 */
class MediaSubscriber
{
    public function __construct(
        private readonly MediaPathUpdater $updater,
        private readonly AbstractUrlGenerator $generator,
        private readonly UrlGeneratorInterface $legacyGenerator
    ) {
    }

    public function written(EntityWrittenEvent $event): void
    {
        if (empty($event->getIds())) {
            return;
        }
        if ($event->getEntityName() === MediaDefinition::ENTITY_NAME) {
            $this->updater->updateMedia($event->getIds());

            return;
        }

        if ($event->getEntityName() === MediaThumbnailDefinition::ENTITY_NAME) {
            $this->updater->updateThumbnails($event->getIds());
        }
    }

    /**
     * @param iterable<Entity> $entities
     */
    public function loaded(iterable $entities): void
    {
        /** @var array<string, string> $path */
        $paths = [];

        foreach ($entities as $entity) {
            $paths[$entity->getUniqueIdentifier()] = $entity->get('path');
        }

        $urls = $this->generator->buildAbsolute(\array_filter($paths));

        foreach ($entities as $entity) {
            $id = $entity->getUniqueIdentifier();

            if (isset($urls[$id])) {
                $entity->assign(['url' => $urls[$id]]);

                continue;
            }

            if (!$entity->has('thumbnails')) {
                continue;
            }

            $this->loaded($entity->get('thumbnails'));
        }
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed, this function is only used to fallback to legacy media url generation. With 6.6, all media paths should be stored in the database.
     */
    public function legacy(EntityLoadedEvent $event): void
    {
        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
            if (!$media->hasFile() || $media->isPrivate()) {
                continue;
            }

            if (!empty($media->getUrl())) {
                continue;
            }

            $media->setUrl($this->legacyGenerator->getAbsoluteMediaUrl($media));

            foreach ($media->getThumbnails() as $thumbnail) {
                if ($thumbnail->getUrl() !== null) {
                    continue;
                }

                $thumbnail->setUrl(
                    $this->legacyGenerator->getAbsoluteThumbnailUrl($media, $thumbnail)
                );
            }
        }
    }
}
