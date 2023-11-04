<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1605861407RuleAssociationsToRestrict extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1605861407;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product_price`
                DROP FOREIGN KEY `fk.product_price.rule_id`;
        ');

        $connection->executeStatement('
            ALTER TABLE product_price
            ADD CONSTRAINT `fk.product_price.rule_id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');

        $connection->executeStatement('
            ALTER TABLE `shipping_method_price`
            DROP FOREIGN KEY `fk.shipping_method_price.rule_id`,
              DROP FOREIGN KEY `fk.shipping_method_price.calculation_rule_id`;
        ');

        $connection->executeStatement('
            ALTER TABLE shipping_method_price
                ADD CONSTRAINT `fk.shipping_method_price.rule_id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                ADD CONSTRAINT `fk.shipping_method_price.calculation_rule_id` FOREIGN KEY (`calculation_rule_id`)
                REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');

        $connection->executeStatement('
            ALTER TABLE `payment_method`
              DROP FOREIGN KEY `fk.payment_method.availability_rule_id`;
        ');

        $connection->executeStatement('
            ALTER TABLE payment_method
            ADD CONSTRAINT `fk.payment_method.availability_rule_id` FOREIGN KEY (`availability_rule_id`)
                REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
