<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1675082889DropUnusedTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675082889;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `message_queue_stats`');
        $connection->executeStatement('DROP TABLE IF EXISTS `mail_template_sales_channel`');
        $connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_rule`');

        if (EntityDefinitionQueryHelper::columnExists($connection, 'customer_address', 'vat_id')) {
            $connection->executeStatement('ALTER TABLE `customer_address` DROP COLUMN `vat_id`');
        }

        if (EntityDefinitionQueryHelper::columnExists($connection, 'customer', 'newsletter')) {
            $connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `newsletter`');
        }

        if (EntityDefinitionQueryHelper::columnExists($connection, 'product', 'whitelist_ids')) {
            $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `whitelist_ids`');
        }

        if (EntityDefinitionQueryHelper::columnExists($connection, 'product', 'blacklist_ids')) {
            $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `blacklist_ids`');
        }

        $this->removeTrigger($connection, 'customer_address_vat_id_insert');
        $this->removeTrigger($connection, 'customer_address_vat_id_update');
        $this->removeTrigger($connection, 'order_cash_rounding_insert');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
