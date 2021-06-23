<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1600676671OrderLineItemCoverMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600676671;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate(
            'UPDATE order_line_item
                LEFT JOIN media ON media.id = order_line_item.cover_id
             SET cover_id = NULL
             WHERE media.id IS NULL'
        );

        $sql = <<<'SQL'
            ALTER TABLE `order_line_item`
            DROP FOREIGN KEY `fk.order_line_item.cover_id`
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<'SQL'
            ALTER TABLE `order_line_item`
            ADD CONSTRAINT `fk.order_line_item.cover_id`
            FOREIGN KEY (`cover_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL;

        $connection->executeUpdate($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
