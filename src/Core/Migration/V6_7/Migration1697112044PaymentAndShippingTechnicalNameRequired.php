<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697112044PaymentAndShippingTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697112044;
    }

    public function update(Connection $connection): void
    {
        $manager = $connection->createSchemaManager();

        $columns = $manager->listTableColumns('payment_method');
        if (\array_key_exists('technical_name', $columns) && !$columns['technical_name']->getNotnull()) {
            $connection->executeStatement(
                'ALTER TABLE `payment_method`
                 MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL'
            );
        }

        $columns = $manager->listTableColumns('shipping_method');
        if (\array_key_exists('technical_name', $columns) && !$columns['technical_name']->getNotnull()) {
            $connection->executeStatement(
                'ALTER TABLE `shipping_method`
                 MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL'
            );
        }
    }
}
