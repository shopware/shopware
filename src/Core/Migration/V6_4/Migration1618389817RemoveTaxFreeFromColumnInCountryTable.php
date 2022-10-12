<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1618389817RemoveTaxFreeFromColumnInCountryTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618389817;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `country` DROP COLUMN `tax_free_from`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
