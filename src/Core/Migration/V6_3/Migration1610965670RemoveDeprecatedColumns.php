<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1610965670RemoveDeprecatedColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1610965670;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropDecimalPrecisionColumn($connection);

        $this->dropPurchasePrice($connection);

        $this->dropCustomFields($connection);
    }

    private function dropDecimalPrecisionColumn(Connection $connection): void
    {
        try {
            $connection->executeUpdate(
                '
                DROP TRIGGER `currency_cash_rounding_insert`'
            );
        } catch (\Throwable $e) {
        }

        try {
            $connection->executeUpdate(
                '
                DROP TRIGGER `currency_cash_rounding_update`'
            );
        } catch (\Throwable $e) {
        }

        try {
            $connection->executeUpdate(
                '
                ALTER TABLE `currency`
                DROP COLUMN `decimal_precision`'
            );
        } catch (\Throwable $e) {
        }
    }

    private function dropPurchasePrice(Connection $connection): void
    {
        try {
            $connection->executeUpdate(
                '
                DROP TRIGGER `product_purchase_prices_insert`'
            );
        } catch (\Throwable $e) {
        }

        try {
            $connection->executeUpdate(
                '
                DROP TRIGGER `product_purchase_prices_update`'
            );
        } catch (\Throwable $e) {
        }

        try {
            $connection->executeUpdate(
                '
                ALTER TABLE `product`
                DROP COLUMN `purchase_price`'
            );
        } catch (\Throwable $e) {
        }
    }

    private function dropCustomFields(Connection $connection): void
    {
        try {
            $connection->executeUpdate(
                '
                ALTER TABLE `customer_wishlist_product`
                DROP COLUMN `custom_fields`'
            );
        } catch (\Throwable $e) {
        }
    }
}
