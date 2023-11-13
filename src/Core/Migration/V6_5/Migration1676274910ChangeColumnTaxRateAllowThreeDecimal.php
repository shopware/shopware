<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('system-settings')]
class Migration1676274910ChangeColumnTaxRateAllowThreeDecimal extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1676274910;
    }

    public function update(Connection $connection): void
    {
        $sqlUpdateToTaxTable = <<<SQL
            ALTER TABLE tax
            MODIFY COLUMN `tax_rate` DECIMAL(10,3);
        SQL;

        $connection->executeStatement($sqlUpdateToTaxTable);

        $sqlUpdateToTaxRuleTable = <<<SQL
            ALTER TABLE tax_rule
            MODIFY COLUMN `tax_rate` DOUBLE(10,3);
        SQL;

        $connection->executeStatement($sqlUpdateToTaxRuleTable);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
