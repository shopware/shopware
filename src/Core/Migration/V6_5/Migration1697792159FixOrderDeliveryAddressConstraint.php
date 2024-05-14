<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697792159FixOrderDeliveryAddressConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697792159;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `order_delivery`
                SET
                    `shipping_order_address_id` = NULL,
                    `shipping_order_address_version_id` = NULL
            WHERE
                NOT EXISTS (
                    SELECT 1
                    FROM `order_address`
                    WHERE
                        `order_address`.`id` = `order_delivery`.`shipping_order_address_id`
                        AND `order_address`.`version_id` = `order_delivery`.`shipping_order_address_version_id`
                );
        ');

        if ($this->keyExists($connection)) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `order_delivery`
            ADD CONSTRAINT `fk.order_delivery.shipping_order_address_id`
            FOREIGN KEY (`shipping_order_address_id`, `shipping_order_address_version_id`)
            REFERENCES `order_address` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    private function keyExists(Connection $connection): bool
    {
        return $connection->executeQuery(
            'SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = "order_delivery"
            AND CONSTRAINT_NAME = "fk.order_delivery.shipping_order_address_id"'
        )->fetchOne() !== false;
    }
}
