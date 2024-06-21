<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Application;

use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * The media url loader is responsible for generating the urls for media and thumbnail entities.
 *
 * It can be used as service or can be triggered via the event dispatcher, by dispatching the `media.loaded` event
 * or delegate an iterable event of entities to the `loaded` function.
 *
 * @final
 */
#[Package('buyers-experience')]
class MediaUrlLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractMediaUrlGenerator $generator,
        private readonly RemoteThumbnailLoader $remoteThumbnailLoader,
        private readonly bool $remoteThumbnailsEnable = false
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
        if ($this->remoteThumbnailsEnable) {
            $this->remoteThumbnailLoader->load($entities);

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
