<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1742199548MeasurementSystem extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1742199548;
    }

    public function update(Connection $connection): void
    {
        $this->addMeasurementSystemTables($connection);
        $this->addDefaultMeasurementSystem($connection);

        $this->addSalesChannelDomainColumns($connection);
        $this->addSalesChannelColumns($connection);
    }

    private function addMeasurementSystemTables(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_system` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_system_translation` (
            `name` VARCHAR(255) NULL,
            `measurement_system_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measurement_system_id`,`language_id`),
            CONSTRAINT `fk.measurement_system_translation.measurement_system_id` FOREIGN KEY (`measurement_system_id`)
              REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measurement_system_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_display_unit` (
              `id` BINARY(16) NOT NULL,
              `measurement_system_id` BINARY(16) NOT NULL,
              `default` TINYINT(1) DEFAULT 0 NOT NULL,
              `type` VARCHAR(20) NOT NULL,
              `short_name` VARCHAR(20) NOT NULL,
              `factor` DOUBLE NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.measurement_display_unit.measurement_system_id` FOREIGN KEY (`measurement_system_id`)
                REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_display_unit_translation` (
            `name` VARCHAR(255) NULL,
            `measurement_display_unit_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measurement_display_unit_id`,`language_id`),
            CONSTRAINT `fk.measurement_display_unit_translation.unit_id` FOREIGN KEY (`measurement_display_unit_id`)
              REFERENCES `measurement_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measurement_display_unit_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addDefaultMeasurementSystem(Connection $connection): void
    {
        $metricId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric'));
        $imperialId = Uuid::fromHexToBytes(Uuid::fromStringToHex('imperial'));

        $metricExists = $connection->fetchOne('SELECT 1 FROM `measurement_system` WHERE `id` = :id', ['id' => $metricId]);
        if (!$metricExists) {
            $connection->insert(
                'measurement_system',
                [
                    'id' => $metricId,
                    'technical_name' => 'metric',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $this->importTranslation(
                'measurement_system_translation',
                new Translations(
                    ['measurement_system_id' => $metricId, 'name' => 'Metrisches System'],
                    ['measurement_system_id' => $metricId, 'name' => 'Metric system']
                ),
                $connection
            );
        }

        $imperialExists = $connection->fetchOne('SELECT 1 FROM `measurement_system` WHERE `id` = :id', ['id' => $imperialId]);
        if (!$imperialExists) {
            $connection->insert(
                'measurement_system',
                [
                    'id' => $imperialId,
                    'technical_name' => 'imperial',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $this->importTranslation(
                'measurement_system_translation',
                new Translations(
                    ['measurement_system_id' => $imperialId, 'name' => 'Kaiserlich System'],
                    ['measurement_system_id' => $imperialId, 'name' => 'Imperial system']
                ),
                $connection
            );
        }

        $units = [
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'length', 'short_name' => 'm', 'factor' => 1000, 'name_en' => 'Meter', 'name_de' => 'Zähler'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'length', 'short_name' => 'cm', 'factor' => 10, 'name_en' => 'Centimeter', 'name_de' => 'Zentimeter'],
            ['id' => Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-mm')), 'measurement_system_id' => $metricId, 'default' => 1, 'type' => 'length', 'short_name' => 'mm', 'factor' => 1, 'name_en' => 'Millimeter', 'name_de' => 'Millimeter'],
            ['id' => Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-kg')), 'measurement_system_id' => $metricId, 'default' => 1, 'type' => 'weight', 'short_name' => 'kg', 'factor' => 1, 'name_en' => 'Kilogram', 'name_de' => 'Kilogramm'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'weight', 'short_name' => 'g', 'factor' => 0.001, 'name_en' => 'Gram', 'name_de' => 'Gramm'],

            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 1, 'type' => 'length', 'short_name' => 'in', 'factor' => 25.4, 'name_en' => 'Inch', 'name_de' => 'Zoll'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'length', 'short_name' => 'ft', 'factor' => 304.8, 'name_en' => 'Foot', 'name_de' => 'Fuß'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'length', 'short_name' => 'yd', 'factor' => 914.4, 'name_en' => 'Yard', 'name_de' => 'Yard'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 1, 'type' => 'weight', 'short_name' => 'lb', 'factor' => 0.453592, 'name_en' => 'Pound', 'name_de' => 'Pfund'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'weight', 'short_name' => 'oz', 'factor' => 0.0283495, 'name_en' => 'Ounce', 'name_de' => 'Unze'],
        ];

        $dbUnits = $connection->fetchOne('SELECT 1 FROM `measurement_display_unit`');
        if ($dbUnits) {
            return;
        }

        foreach ($units as $unit) {
            $connection->executeStatement('
                INSERT INTO `measurement_display_unit`
                (`id`, `measurement_system_id`, `default`, `type`, `short_name`, `factor`, `created_at`)
                VALUES (:id, :measurementSystemId, :default, :type, :shortName, :factor, :createdAt)
            ', [
                'id' => $unit['id'],
                'measurementSystemId' => $unit['measurement_system_id'],
                'default' => $unit['default'],
                'type' => $unit['type'],
                'shortName' => $unit['short_name'],
                'factor' => $unit['factor'],
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $this->importTranslation(
                'measurement_display_unit_translation',
                new Translations(
                    ['measurement_display_unit_id' => $unit['id'], 'name' => $unit['name_de']],
                    ['measurement_display_unit_id' => $unit['id'], 'name' => $unit['name_en']]
                ),
                $connection
            );
        }
    }

    private function addSalesChannelDomainColumns(Connection $connection): void
    {
        if (
            $this->columnExists($connection, 'sales_channel_domain', 'measurement_system_id')
            || $this->columnExists($connection, 'sales_channel_domain', 'weight_unit_id')
            || $this->columnExists($connection, 'sales_channel_domain', 'length_unit_id')
        ) {
            return;
        }

        $metricId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric'));
        $weightUnitId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-kg'));
        $lengthUnitId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-mm'));

        $connection->executeStatement('
            ALTER TABLE `sales_channel_domain`
            ADD COLUMN `measurement_system_id` BINARY(16) NOT NULL DEFAULT \'' . $metricId . '\',
            ADD COLUMN `weight_unit_id` BINARY(16) NOT NULL DEFAULT \'' . $weightUnitId . '\',
            ADD COLUMN `length_unit_id` BINARY(16) NOT NULL DEFAULT \'' . $lengthUnitId . '\',
            ADD CONSTRAINT `fk.sales_channel_domain.measurement_system_id` FOREIGN KEY (`measurement_system_id`) REFERENCES `measurement_system` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.sales_channel_domain.weight_unit_id` FOREIGN KEY (`weight_unit_id`) REFERENCES `measurement_display_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.sales_channel_domain.length_unit_id` FOREIGN KEY (`length_unit_id`) REFERENCES `measurement_display_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');
    }

    private function addSalesChannelColumns(Connection $connection): void
    {
        if (
            $this->columnExists($connection, 'sales_channel', 'measurement_system_id')
            || $this->columnExists($connection, 'sales_channel', 'weight_unit_id')
            || $this->columnExists($connection, 'sales_channel', 'length_unit_id')
        ) {
            return;
        }

        $metricId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric'));
        $weightUnitId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-kg'));
        $lengthUnitId = Uuid::fromHexToBytes(Uuid::fromStringToHex('metric-mm'));

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD COLUMN `measurement_system_id` BINARY(16) NOT NULL DEFAULT \'' . $metricId . '\',
            ADD COLUMN `weight_unit_id` BINARY(16) NOT NULL DEFAULT \'' . $weightUnitId . '\',
            ADD COLUMN `length_unit_id` BINARY(16) NOT NULL DEFAULT \'' . $lengthUnitId . '\',
                ADD CONSTRAINT `fk.sales_channel.measurement_system_id` FOREIGN KEY (`measurement_system_id`) REFERENCES `measurement_system` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.sales_channel.weight_unit_id` FOREIGN KEY (`weight_unit_id`) REFERENCES `measurement_display_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.sales_channel.length_unit_id` FOREIGN KEY (`length_unit_id`) REFERENCES `measurement_display_unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');
    }
}
