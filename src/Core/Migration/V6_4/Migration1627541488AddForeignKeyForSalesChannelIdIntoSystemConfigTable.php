<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1627541488AddForeignKeyForSalesChannelIdIntoSystemConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1627541488;
    }

    public function update(Connection $connection): void
    {
        $this->deleteConfigOfNonexistentSalesChannel($connection);
        $this->addSalesChannelIdForeignKey($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function deleteConfigOfNonexistentSalesChannel(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config`
            WHERE `sales_channel_id` IS NOT NULL
            AND `sales_channel_id` NOT IN (SELECT `id` FROM `sales_channel`)'
        );
    }

    private function addSalesChannelIdForeignKey(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `system_config`
            ADD CONSTRAINT `fk.system_config.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }
}
