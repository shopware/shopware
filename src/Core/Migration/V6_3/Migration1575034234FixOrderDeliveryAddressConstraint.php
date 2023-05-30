<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1575034234FixOrderDeliveryAddressConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575034234;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `order_delivery` DROP FOREIGN KEY `fk.order_delivery.shipping_order_address_id`
        ');

        $connection->executeStatement('
            ALTER TABLE `order_delivery`
            ADD CONSTRAINT `fk.order_delivery.shipping_order_address_id`
            FOREIGN KEY (`shipping_order_address_id`, `shipping_order_address_version_id`)
            REFERENCES `order_address` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
