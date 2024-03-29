<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1704703562ScheduleMediaPathIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1704703562;
    }

    public function update(Connection $connection): void
    {
        // schedule indexer again to fix media path and reindex the denormalized thumbnails
        $this->registerIndexer($connection, 'media.path.post_update');
    }
}
