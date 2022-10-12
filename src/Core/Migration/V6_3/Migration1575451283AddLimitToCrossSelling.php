<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1575451283AddLimitToCrossSelling extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575451283;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product_cross_selling`
            ADD COLUMN `limit` INT(11) NOT NULL DEFAULT 24 AFTER `active`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
