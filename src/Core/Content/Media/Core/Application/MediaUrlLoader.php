<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Application;

use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * The media url loader is responsible for generating the urls for media and thumbnail entities.
 *
 * It can be used as service or can be triggered via the event dispatcher, by dispatching the `media.loaded` event
 * or delegate an iterable event of entities to the `loaded` function.
 *
 * @final
 */
#[Package('content')]
class MediaUrlLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractMediaUrlGenerator $generator,
        private readonly UrlGeneratorInterface $legacyGenerator
    ) {
    }

    /**
     * Collects all urls of the provided entities and triggers the AbstractMediaUrlGenerator to generate the urls.
     * The generated urls will be assigned to the entities afterward.
     *
     * @param iterable<Entity> $entities
     */
    public function loaded(iterable $entities): void
    {
        if (!self::newBehavior()) {
            return;
        }

        $mapping = $this->map($entities);

        if (empty($mapping)) {
            return;
        }

        $urls = $this->generator->generate($mapping);

        foreach ($entities as $entity) {
            if (!isset($urls[$entity->getUniqueIdentifier()])) {
                continue;
            }

            $entity->assign(['url' => $urls[$entity->getUniqueIdentifier()]]);

            if (!$entity->has('thumbnails')) {
                continue;
            }

            /** @var Entity $thumbnail */
            foreach ($entity->get('thumbnails') as $thumbnail) {
                if (!isset($urls[$thumbnail->getUniqueIdentifier()])) {
                    continue;
                }

                $thumbnail->assign(['url' => $urls[$thumbnail->getUniqueIdentifier()]]);
            }
        }
    }

    /**
     * @param iterable<MediaEntity> $entities
     *
     * @deprecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, this function is only used to fall back to legacy media url generation. With 6.6, all media paths should be stored in the database.
     */
    public function legacyPath(iterable $entities): void
    {
        if (self::newBehavior()) {
            return;
        }

        foreach ($entities as $media) {
            if (!$media->hasFile() || $media->isPrivate()) {
                continue;
            }

            if (!empty($media->getPath())) {
                continue;
            }

            $media->setPath($this->legacyGenerator->getRelativeMediaUrl($media));

            if ($media->getThumbnails() === null) {
                continue;
            }

            foreach ($media->getThumbnails() as $thumbnail) {
                if (!empty($thumbnail->getPath())) {
                    continue;
                }

                $thumbnail->setPath(
                    $this->legacyGenerator->getRelativeThumbnailUrl($media, $thumbnail)
                );
            }
        }
    }

    /**
     * @param iterable<MediaEntity> $entities
     *
     * @deprecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, this function is only used to fall back to legacy media url generation. With 6.6, all media paths should be stored in the database.
     */
    public function legacy(iterable $entities): void
    {
        if (self::newBehavior()) {
            return;
        }

        foreach ($entities as $media) {
            if (!$media instanceof MediaEntity) {
                continue;
            }
            if (!$media->hasFile() || $media->isPrivate()) {
                continue;
            }

            if (!empty($media->getUrl())) {
                continue;
            }

            $media->setUrl($this->legacyGenerator->getAbsoluteMediaUrl($media));

            if ($media->getThumbnails() === null) {
                continue;
            }

            foreach ($media->getThumbnails() as $thumbnail) {
                if (!empty($thumbnail->getUrl())) {
                    continue;
                }

                $thumbnail->setUrl(
                    $this->legacyGenerator->getAbsoluteThumbnailUrl($media, $thumbnail)
                );
            }
        }
    }

    private static function newBehavior(): bool
    {
        return Feature::isActive('v6.6.0.0') || Feature::isActive('media_path');
    }

    /**
     * @param iterable<Entity> $entities
     *
     * @return array<string, UrlParams>
     */
    private function map(iterable $entities): array
    {
        $mapped = [];

        foreach ($entities as $entity) {
            if (!$entity->has('path') || empty($entity->get('path'))) {
                continue;
            }
            // don't generate private urls
            if (!$entity->has('private') || $entity->get('private')) {
                continue;
            }

            $mapped[$entity->getUniqueIdentifier()] = UrlParams::fromMedia($entity);

            if (!$entity->has('thumbnails')) {
                continue;
            }

            /** @var Entity $thumbnail */
            foreach ($entity->get('thumbnails') as $thumbnail) {
                \assert($thumbnail instanceof Entity);
                if (!$thumbnail->has('path') || empty($thumbnail->get('path'))) {
                    continue;
                }

                $mapped[$thumbnail->getUniqueIdentifier()] = UrlParams::fromThumbnail($thumbnail);
            }
        }

        return $mapped;
    }
}
