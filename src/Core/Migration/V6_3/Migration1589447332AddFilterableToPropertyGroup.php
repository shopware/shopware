<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1589447332AddFilterableToPropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589447332;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `property_group`
            ADD COLUMN `filterable` TINYINT(1) NOT NULL DEFAULT 1
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
