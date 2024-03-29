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
    private const FK_DROPS = [
        'fk.cart.country_id',
        'fk.cart.currency_id',
        'fk.cart.payment_method_id',
        'fk.cart.sales_channel_id',
        'fk.cart.shipping_method_id',
        'fk.cart.customer_id',
    ];

    private const USELESS_COLUMNS = [
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
        foreach (self::FK_DROPS as $fk) {
            try {
                $connection->executeStatement(sprintf('ALTER TABLE `cart` DROP FOREIGN KEY `%s`', $fk));
            } catch (\Exception) {
                // it's fine that the FK has been already deleted
            }
        }

        foreach (self::FK_DROPS as $fk) {
            try {
                $connection->executeStatement(sprintf('ALTER TABLE `cart` DROP INDEX `%s`', $fk));
            } catch (\Exception) {
                // it's fine that the index has been already deleted
            }
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
        foreach (self::USELESS_COLUMNS as $column) {
            try {
                $connection->executeStatement(sprintf('ALTER TABLE `cart` DROP COLUMN `%s`', $column));
            } catch (\Exception) {
                // it's fine that the column has been already deleted
            }
        }
    }
}
