<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1578044453AddedNavigationDepth extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578044453;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `sales_channel` ADD `navigation_category_depth` int NOT NULL DEFAULT \'2\' AFTER `navigation_category_version_id`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
