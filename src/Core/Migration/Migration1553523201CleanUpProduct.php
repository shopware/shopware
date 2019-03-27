<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553523201CleanUpProduct extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553523201;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `product`
            DROP COLUMN `sales`,
            DROP COLUMN `allow_notification`,
            DROP COLUMN `min_stock`,
            DROP COLUMN `position`,
            MODIFY COLUMN `shipping_free` TINYINT(1) NULL
            ;'
        );

        $connection->exec(
            'ALTER TABLE `product_translation`
            DROP COLUMN `description`,
            CHANGE COLUMN `description_long` `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL
            ;'
        );
    }
}
