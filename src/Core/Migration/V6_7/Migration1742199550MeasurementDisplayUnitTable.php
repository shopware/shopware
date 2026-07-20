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
class Migration1742199550MeasurementDisplayUnitTable extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1742199550;
    }

    public function update(Connection $connection): void
    {
        $this->createMeasurementDisplayUnitTable($connection);
        $this->addDefaultMeasurementUnits($connection);
    }

    private function createMeasurementDisplayUnitTable(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_display_unit` (
              `id` BINARY(16) NOT NULL,
              `measurement_system_id` BINARY(16) NOT NULL,
              `default` TINYINT(1) DEFAULT 0 NOT NULL,
              `type` VARCHAR(20) NOT NULL,
              `short_name` VARCHAR(20) NOT NULL,
              `factor` DOUBLE NOT NULL,
              `precision` INT NOT NULL DEFAULT 3,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.measurement_display_unit.short_name` (`short_name`),
              CONSTRAINT `fk.measurement_display_unit.measurement_system_id` FOREIGN KEY (`measurement_system_id`)
                REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_display_unit_translation` (
            `name` VARCHAR(255) NULL,
            `measurement_display_unit_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `custom_fields` JSON NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measurement_display_unit_id`,`language_id`),
            CONSTRAINT `json.measurement_display_unit_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
            CONSTRAINT `fk.measurement_display_unit_translation.unit_id` FOREIGN KEY (`measurement_display_unit_id`)
              REFERENCES `measurement_display_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measurement_display_unit_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addDefaultMeasurementUnits(Connection $connection): void
    {
        $metricId = $connection->fetchOne('SELECT `id` FROM `measurement_system` WHERE `technical_name` = :technicalName', ['technicalName' => 'metric']);
        $imperialId = $connection->fetchOne('SELECT `id` FROM `measurement_system` WHERE `technical_name` = :technicalName', ['technicalName' => 'imperial']);

        $units = [
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'length', 'short_name' => 'm', 'factor' => 1000, 'precision' => 2, 'name_en' => 'Meter', 'name_de' => 'Meter'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'length', 'short_name' => 'cm', 'factor' => 10, 'precision' => 2, 'name_en' => 'Centimeter', 'name_de' => 'Zentimeter'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 1, 'type' => 'length', 'short_name' => 'mm', 'factor' => 1, 'precision' => 2, 'name_en' => 'Millimeter', 'name_de' => 'Millimeter'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 1, 'type' => 'weight', 'short_name' => 'kg', 'factor' => 1, 'precision' => 2, 'name_en' => 'Kilogram', 'name_de' => 'Kilogramm'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $metricId, 'default' => 0, 'type' => 'weight', 'short_name' => 'g', 'factor' => 0.001, 'precision' => 2, 'name_en' => 'Gram', 'name_de' => 'Gramm'],

            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 1, 'type' => 'length', 'short_name' => 'in', 'factor' => 25.4, 'precision' => 2, 'name_en' => 'Inch', 'name_de' => 'Zoll'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'length', 'short_name' => 'ft', 'factor' => 304.8, 'precision' => 2, 'name_en' => 'Foot', 'name_de' => 'Fuß'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'length', 'short_name' => 'yd', 'factor' => 914.4, 'precision' => 2, 'name_en' => 'Yard', 'name_de' => 'Yard'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 1, 'type' => 'weight', 'short_name' => 'lb', 'factor' => 0.453592, 'precision' => 2, 'name_en' => 'Pound', 'name_de' => 'Pfund'],
            ['id' => Uuid::randomBytes(), 'measurement_system_id' => $imperialId, 'default' => 0, 'type' => 'weight', 'short_name' => 'oz', 'factor' => 0.0283495, 'precision' => 2, 'name_en' => 'Ounce', 'name_de' => 'Unze'],
        ];

        $dbUnits = $connection->fetchOne('SELECT 1 FROM `measurement_display_unit`');
        if ($dbUnits) {
            return;
        }

        foreach ($units as $unit) {
            $connection->executeStatement('
                INSERT INTO `measurement_display_unit`
                (`id`, `measurement_system_id`, `default`, `type`, `short_name`, `factor`, `precision`, `created_at`)
                VALUES (:id, :measurementSystemId, :default, :type, :shortName, :factor, :precision, :createdAt)
            ', [
                'id' => $unit['id'],
                'measurementSystemId' => $unit['measurement_system_id'],
                'default' => $unit['default'],
                'type' => $unit['type'],
                'shortName' => $unit['short_name'],
                'factor' => $unit['factor'],
                'precision' => $unit['precision'],
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
}
