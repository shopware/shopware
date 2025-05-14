<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1745319883AddDefaultConfigForMeasurementSystem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1745319883;
    }

    public function update(Connection $connection): void
    {
        $query = 'INSERT IGNORE INTO system_config SET
                    id = :id,
                    configuration_value = :configValue,
                    configuration_key = :configKey,
                    created_at = :createdAt;';

        $metricId = $connection->fetchOne('SELECT id FROM `measurement_system` WHERE `technical_name` = "metric"');
        if ($metricId) {
            $connection->executeStatement($query, [
                'id' => Uuid::randomBytes(),
                'configKey' => 'core.measurementSystem.typeId',
                'configValue' => \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($metricId)),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $units = $connection->fetchAllKeyValue('SELECT id, type FROM `measurement_display_unit` WHERE short_name IN (:names)', [
            'names' => ['mm', 'kg'],
        ], [
            'names' => ArrayParameterType::BINARY,
        ]);

        foreach ($units as $id => $unitType) {
            $configKey = $unitType === 'length' ? 'core.measurementSystem.lengthUnitId' : 'core.measurementSystem.weightUnitId';
            $connection->executeStatement($query, [
                'id' => Uuid::randomBytes(),
                'configKey' => $configKey,
                'configValue' => \sprintf('{"_value": "%s"}', Uuid::fromBytesToHex($id)),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }
}
