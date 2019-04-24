<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1556034435AddLineItemCoverId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1556034435;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `order_line_item` ADD `cover_id` binary(16) NULL AFTER `description`;');
        $connection->executeUpdate('
            ALTER TABLE `order_line_item` 
            ADD CONSTRAINT `fk.order_line_item.cover_id` FOREIGN KEY (`cover_id`) 
            REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
