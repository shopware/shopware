<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
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
            $connection->executeStatement(
                'DROP TRIGGER `currency_cash_rounding_insert`'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement(
                'DROP TRIGGER `currency_cash_rounding_update`'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement(
                'ALTER TABLE `currency`
                DROP COLUMN `decimal_precision`'
            );
        } catch (\Throwable) {
        }
    }

    private function dropPurchasePrice(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'DROP TRIGGER `product_purchase_prices_insert`'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement(
                'DROP TRIGGER `product_purchase_prices_update`'
            );
        } catch (\Throwable) {
        }

        try {
            $connection->executeStatement(
                'ALTER TABLE `product`
                DROP COLUMN `purchase_price`'
            );
        } catch (\Throwable) {
        }
    }

    private function dropCustomFields(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'ALTER TABLE `customer_wishlist_product`
                DROP COLUMN `custom_fields`'
            );
        } catch (\Throwable) {
        }
    }
}
