<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1707064042CartRemoveFK extends MigrationStep
{
    private const OBSOLETE_FOREIGN_KEYS = [
        'fk.cart.country_id',
        'fk.cart.currency_id',
        'fk.cart.payment_method_id',
        'fk.cart.sales_channel_id',
        'fk.cart.shipping_method_id',
        'fk.cart.customer_id',
    ];

    private const OBSOLETE_COLUMNS = [
        'price',
        'line_item_count',
        'currency_id',
        'shipping_method_id',
        'customer_id',
        'payment_method_id',
        'country_id',
        'sales_channel_id',
        'updated_at',
    ];

    public function getCreationTimestamp(): int
    {
        return 1707064042;
    }

    public function update(Connection $connection): void
    {
        foreach (self::OBSOLETE_FOREIGN_KEYS as $fk) {
            $this->dropForeignKeyIfExists($connection, 'cart', $fk);
        }

        foreach (self::OBSOLETE_FOREIGN_KEYS as $fk) {
            $this->dropIndexIfExists($connection, 'cart', $fk);
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `price` `price` FLOAT NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `line_item_count` `line_item_count` VARCHAR(42) NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `currency_id` `currency_id` BINARY(16) NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `shipping_method_id` `shipping_method_id` BINARY(16) NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `payment_method_id` `payment_method_id` BINARY(16) NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `country_id` `country_id` BINARY(16) NULL');
        } catch (\Exception) {
        }

        try {
            $connection->executeStatement('ALTER TABLE `cart` CHANGE COLUMN `sales_channel_id` `sales_channel_id` BINARY(16) NULL');
        } catch (\Exception) {
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        foreach (self::OBSOLETE_COLUMNS as $column) {
            $this->dropColumnIfExists($connection, 'cart', $column);
        }
    }
}
