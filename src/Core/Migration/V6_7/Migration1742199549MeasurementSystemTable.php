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
class Migration1742199549MeasurementSystemTable extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1742199549;
    }

    public function update(Connection $connection): void
    {
        $this->createMeasurementSystemTable($connection);
        $this->addDefaultMeasurementSystems($connection);
    }

    private function createMeasurementSystemTable(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_system` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.measurement_system.technical_name` (`technical_name`)
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `measurement_system_translation` (
            `name` VARCHAR(255) NULL,
            `measurement_system_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `custom_fields` JSON NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`measurement_system_id`,`language_id`),
            CONSTRAINT `json.measurement_system_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
            CONSTRAINT `fk.measurement_system_translation.measurement_system_id` FOREIGN KEY (`measurement_system_id`)
              REFERENCES `measurement_system` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.measurement_system_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addDefaultMeasurementSystems(Connection $connection): void
    {
        $metricId = Uuid::randomBytes();
        $imperialId = Uuid::randomBytes();

        $metricExists = $connection->fetchOne('SELECT 1 FROM `measurement_system` WHERE `technical_name` = :technicalName', ['technicalName' => 'metric']);
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

        $imperialExists = $connection->fetchOne('SELECT 1 FROM `measurement_system` WHERE `technical_name` = :technicalName', ['technicalName' => 'imperial']);
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
                    ['measurement_system_id' => $imperialId, 'name' => 'Angloamerikanisches Maßsystem'],
                    ['measurement_system_id' => $imperialId, 'name' => 'Imperial system']
                ),
                $connection
            );
        }
    }
}
