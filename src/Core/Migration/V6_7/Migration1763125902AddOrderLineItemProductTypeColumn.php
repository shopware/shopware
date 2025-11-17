<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

#[Package('inventory')]
class Migration1763125902AddOrderLineItemProductTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125902;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'order_line_item', 'product_type')) {
            $connection->executeStatement(
                "ALTER TABLE `order_line_item` ADD `product_type` VARCHAR(32) NULL AFTER `type`"
            );
            $connection->executeStatement('CREATE INDEX `idx.order_line_item.product_type` ON `order_line_item` (`product_type`)');
        }

        $connection->executeStatement(<<<'SQL'
            UPDATE `order_line_item` oli
                INNER JOIN `order_line_item_download` olid
                    ON olid.order_line_item_id = oli.id AND olid.order_line_item_version_id = oli.version_id
             SET oli.product_type = 'digital'
             WHERE oli.type = 'product'
            SQL);

        $connection->executeStatement(<<<'SQL'
            UPDATE `order_line_item`
             SET product_type = 'physical'
             WHERE product_type IS NULL
               AND type = 'product'
               AND states IS NOT NULL
               AND JSON_CONTAINS(states, '"is-physical"')
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'order_line_item', 'states');
    }
}
