<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Infrastructure\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('core')]
class MediaPathStorage
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, string> $paths
     */
    public function media(array $paths): void
    {
        $update = $this->connection->prepare('UPDATE media SET path = :path WHERE id = :id');

        foreach ($paths as $id => $path) {
            $update->executeStatement(['path' => $path, 'id' => Uuid::fromHexToBytes($id)]);
        }
    }

    /**
     * @param array<string, string> $paths
     */
    public function thumbnails(array $paths): void
    {
        $update = $this->connection->prepare('UPDATE media_thumbnail SET path = :path WHERE id = :id');

        foreach ($paths as $id => $path) {
            $update->executeStatement([':path' => $path, ':id' => Uuid::fromHexToBytes($id)]);
        }
    }
}
