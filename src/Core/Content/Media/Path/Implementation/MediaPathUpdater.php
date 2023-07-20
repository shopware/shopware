<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaPathUpdater;
use Shopware\Core\Content\Media\Path\Contract\Service\MediaLocationBuilder;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal Concrete implementations of this class should not be extended or used as a base class/type hint.
 */
class MediaPathUpdater extends AbstractMediaPathUpdater
{
    public function __construct(
        private AbstractMediaPathStrategy $strategy,
        private MediaLocationBuilder $builder,
        private Connection $connection,
    ) {
    }

    public function updateMedia(array $ids): void
    {
        $locations = $this->builder->media($ids);

        $paths = $this->strategy->generate($locations);

        $update = $this->connection->prepare('UPDATE media SET path = :path WHERE id = :id');

        foreach ($paths as $id => $path) {
            $update->executeStatement([':path' => $path, ':id' => Uuid::fromHexToBytes($id)]);
        }
    }

    public function updateThumbnails(array $ids): void
    {
        $locations = $this->builder->thumbnails($ids);

        $paths = $this->strategy->generate($locations);

        $update = $this->connection->prepare('UPDATE media_thumbnail SET path = :path WHERE id = :id');

        foreach ($paths as $id => $path) {
            $update->executeStatement([':path' => $path, ':id' => Uuid::fromHexToBytes($id)]);
        }
    }
}
