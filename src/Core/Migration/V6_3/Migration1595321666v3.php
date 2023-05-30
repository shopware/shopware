<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1595321666v3 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595321666;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeTrigger($connection, 'shipping_method_price_new_price_update');
        $this->removeTrigger($connection, 'shipping_method_price_new_price_insert');
        $connection->executeStatement('
            ALTER TABLE `shipping_method_price`
              DROP FOREIGN KEY `fk.shipping_method_price.currency_id`;
        ');
        $connection->executeStatement('
            ALTER TABLE shipping_method_price
                DROP FOREIGN KEY `fk.shipping_method_price.shipping_method_id`;
        ');
        $connection->executeStatement('
            DROP INDEX `fk.shipping_method_price.currency_id` ON shipping_method_price;
        ');

        $connection->executeStatement('
            ALTER TABLE shipping_method_price
                DROP KEY `uniq.shipping_method_quantity_start`;
        ');
        $connection->executeStatement('
            ALTER TABLE `shipping_method_price`
            DROP `price`,
            DROP `currency_id`;
        ');
        $connection->executeStatement('
            ALTER TABLE shipping_method_price
                ADD CONSTRAINT `fk.shipping_method_price.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ');
        $connection->executeStatement('
            CREATE UNIQUE INDEX `uniq.shipping_method_quantity_start`
                ON shipping_method_price (shipping_method_id, rule_id, quantity_start);
        ');
    }
}
