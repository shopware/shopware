<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Core\Application\MediaPathStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore (see \Shopware\Tests\Integration\Core\Content\Media\Infrastructure\Path\MediaPathStorageTest)
 */
#[Package('core')]
class SqlMediaPathStorage implements MediaPathStorage
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
