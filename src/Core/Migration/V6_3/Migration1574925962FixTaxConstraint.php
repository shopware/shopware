<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1574925962FixTaxConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574925962;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `tax_rule_type_translation` DROP FOREIGN KEY `fk.tax_rule_type_translation.tax_rule_type_id`
        ');

        $connection->executeStatement('
            ALTER TABLE `tax_rule_type_translation`
            ADD CONSTRAINT `fk.tax_rule_type_translation.tax_rule_type_id` FOREIGN KEY (`tax_rule_type_id`)
            REFERENCES `tax_rule_type` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
