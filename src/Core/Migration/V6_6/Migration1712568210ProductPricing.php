<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1712568210ProductPricing extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1712568210;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
           CREATE TABLE IF NOT EXISTS product_pricing (
                id BINARY(16) NOT NULL,

                product_id BINARY(16) NOT NULL,
                product_version_id BINARY(16) NOT NULL,

                quantity_start INT NOT NULL,
                quantity_end INT NULL DEFAULT NULL,

                price JSON NULL DEFAULT NULL,
                discount float NULL DEFAULT NULL,

                customer_group_id BINARY(16) NULL DEFAULT NULL,
                sales_channel_id BINARY(16) NULL DEFAULT NULL,
                country_id BINARY(16) NULL DEFAULT NULL,

                created_at DATETIME(3) NOT NULL,
                updated_at DATETIME(3),

                `precision` INT(11) GENERATED ALWAYS AS (
                    IF(customer_group_id IS NULL, 0, 1) +
                    IF(sales_channel_id IS NULL, 0, 1) +
                    IF(country_id IS NULL, 0, 1)
                ),

                PRIMARY KEY (id),

                CONSTRAINT `fk.product_pricing.product_id`
                    FOREIGN KEY (product_id, product_version_id) REFERENCES product (id, version_id) ON DELETE CASCADE ON UPDATE CASCADE,

                CONSTRAINT `fk.product_pricing.customer_group_id`
                    FOREIGN KEY (customer_group_id) REFERENCES customer_group (id) ON DELETE CASCADE ON UPDATE CASCADE,

                CONSTRAINT `fk.product_pricing.sales_channel_id`
                    FOREIGN KEY (sales_channel_id) REFERENCES sales_channel (id) ON DELETE CASCADE ON UPDATE CASCADE,

                CONSTRAINT `fk.product_pricing.country_id`
                    FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');

        $this->updateInheritance($connection, 'product', 'pricing');
    }

    public function updateDestructive(Connection $connection): void
    {
        //        $connection->executeStatement('DROP TABLE IF EXISTS product_price');
        //
        //        if ($this->columnExists($connection, 'product', 'price')) {
        //            $connection->executeStatement('ALTER TABLE product DROP COLUMN price');
        //        }
    }
}
