<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1600676671OrderLineItemCoverMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600676671;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE order_line_item
                LEFT JOIN media ON media.id = order_line_item.cover_id
             SET cover_id = NULL
             WHERE media.id IS NULL'
        );

        $sql = <<<'SQL'
            ALTER TABLE `order_line_item`
            DROP FOREIGN KEY `fk.order_line_item.cover_id`
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            ALTER TABLE `order_line_item`
            ADD CONSTRAINT `fk.order_line_item.cover_id`
            FOREIGN KEY (`cover_id`) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
