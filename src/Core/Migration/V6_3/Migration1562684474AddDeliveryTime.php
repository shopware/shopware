<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1562684474AddDeliveryTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562684474;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD `delivery_time_id` binary(16) NULL AFTER `product_manufacturer_version_id`,
            ADD `deliveryTime` binary(16) NULL AFTER `delivery_time_id`,
            DROP `min_delivery_time`,
            DROP `max_delivery_time`;
        ');

        $connection->executeStatement('UPDATE product SET delivery_time_id = (SELECT id FROM delivery_time LIMIT 1) WHERE parent_id IS NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
