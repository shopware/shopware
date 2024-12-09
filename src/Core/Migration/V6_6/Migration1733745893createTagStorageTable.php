<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1733745893createTagStorageTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733745893;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE invalidation_tags (
                tag VARCHAR(255) NOT NULL PRIMARY KEY
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
