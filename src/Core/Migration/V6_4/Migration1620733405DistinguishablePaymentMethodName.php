<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1620733405DistinguishablePaymentMethodName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620733405;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `payment_method_translation`
            ADD COLUMN `distinguishable_name` VARCHAR(255) NULL AFTER `name`
        ');

        // Existing entities are not migrated or filled with a default value because payment_method_translation.name
        // will be used in case payment_method_translation.distinguishableName is null
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
