<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Domain\Path;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
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
        $mapping = $this->map($entities);

        if (empty($mapping)) {
            return;
        }

        $urls = $this->generator->generate($mapping);

        foreach ($entities as $entity) {
            if (!isset($mapping[$entity->getUniqueIdentifier()])) {
                continue;
            }

            $entity->assign(['url' => $urls[$entity->getUniqueIdentifier()]]);

            if (!$entity->has('thumbnails')) {
                continue;
            }

            /** @var Entity $thumbnail */
            foreach ($entity->get('thumbnails') as $thumbnail) {
                if (!isset($mapping[$thumbnail->getUniqueIdentifier()])) {
                    continue;
                }

                $thumbnail->assign(['url' => $urls[$thumbnail->getUniqueIdentifier()]]);
            }
        }
    }

    /**
     * @deprecated tag:v6.6.0 - reason:remove-subscriber - Will be removed, this function is only used to fall back to legacy media url generation. With 6.6, all media paths should be stored in the database.
     */
    public function legacy(EntityLoadedEvent $event): void
    {
        if (Feature::isActive('v6.6.0.0')) {
            return;
        }

        /** @var MediaEntity $media */
        foreach ($event->getEntities() as $media) {
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

    /**
     * @param iterable<Entity> $entities
     *
     * @return array<string, array{path:string, updatedAt:\DateTimeImmutable|null}>
     */
    private function map(iterable $entities): array
    {
        $mapped = [];

        foreach ($entities as $entity) {
            \assert($entity instanceof Entity);
            if (!$entity->has('path') || empty($entity->get('path'))) {
                continue;
            }

            $mapped[$entity->getUniqueIdentifier()] = [
                'path' => (string) $entity->get('path'),
                'updatedAt' => $entity->get('updatedAt'),
            ];

            if (!$entity->has('thumbnails')) {
                continue;
            }

            /** @var Entity $thumbnail */
            foreach ($entity->get('thumbnails') as $thumbnail) {
                \assert($thumbnail instanceof Entity);
                if (!$thumbnail->has('path') || empty($thumbnail->get('path'))) {
                    continue;
                }

                $mapped[$thumbnail->getUniqueIdentifier()] = [
                    'path' => (string) $thumbnail->get('path'),
                    'updatedAt' => $entity->get('updatedAt'),
                ];
            }
        }

        return $mapped;
    }
}
