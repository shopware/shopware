<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1574082635AddOrderLineItemProductId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574082635;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `order_line_item`
            ADD `product_id` binary(16) NULL AFTER `referenced_id`,
            ADD `product_version_id` binary(16) NULL AFTER `product_id`;        
        ');

        $connection->executeUpdate('UPDATE IGNORE order_line_item SET product_id = UNHEX(referenced_id) WHERE type = \'product\'');

        $connection->executeUpdate('ALTER TABLE `order_line_item` ADD FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
