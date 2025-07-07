<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1742199551SalesChannelDomainMeasurementUnits extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742199551;
    }

    public function update(Connection $connection): void
    {
        $this->addMeasurementUnitsColumn($connection);
    }

    private function addMeasurementUnitsColumn(Connection $connection): void
    {
        if ($this->columnExists($connection, 'sales_channel_domain', 'measurement_units')) {
            return;
        }

        $defaultUnits = \json_encode([
            'system' => 'metric',
            'units' => [
                'length' => 'mm',
                'weight' => 'kg',
            ],
        ]);

        $connection->executeStatement('
            ALTER TABLE `sales_channel_domain`
            ADD COLUMN `measurement_units` JSON NULL;
        ');

        // Set default measurement units for existing domains
        $connection->executeStatement('
            UPDATE `sales_channel_domain`
            SET `measurement_units` = \'' . $defaultUnits . '\'
            WHERE `measurement_units` IS NULL
        ');
    }
}
