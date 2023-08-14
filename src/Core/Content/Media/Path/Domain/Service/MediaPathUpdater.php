<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Domain\Service;

use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaLocationBuilder;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathUpdater;
use Shopware\Core\Content\Media\Path\Infrastructure\Service\MediaPathStorage;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementations of this class should not be extended or used as a base class/type hint.
 */
#[Package('content')]
class MediaPathUpdater extends AbstractMediaPathUpdater
{
    public function __construct(
        private readonly AbstractMediaPathStrategy $strategy,
        private readonly AbstractMediaLocationBuilder $builder,
        private readonly MediaPathStorage $storage
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function updateMedia(iterable $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = $ids instanceof \Traversable ? \iterator_to_array($ids) : $ids;

        $locations = $this->builder->media($ids);

        $paths = $this->strategy->generate($locations);

        $this->storage->media($paths);
    }

    /**
     * {@inheritdoc}
     */
    public function updateThumbnails(iterable $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = $ids instanceof \Traversable ? \iterator_to_array($ids) : $ids;

        $locations = $this->builder->thumbnails($ids);

        $paths = $this->strategy->generate($locations);

        $this->storage->thumbnails($paths);
    }
}
