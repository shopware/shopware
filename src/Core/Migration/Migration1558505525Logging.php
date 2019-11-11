<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1558505525Logging extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558505525;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `log_entry` (
              `id` BINARY(16) NOT NULL,
              `message` VARCHAR(255) NOT NULL,
              `level` SMALLINT NOT NULL,
              `channel` VARCHAR(255) NOT NULL,
              `context` JSON NULL,
              `extra` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) ,
              CONSTRAINT `json.log_entry.context` CHECK (JSON_VALID(`context`)),
              CONSTRAINT `json.log_entry.extra` CHECK (JSON_VALID(`extra`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.logging.cleanupInterval',
            'configuration_value' => '{"_value": "86400"}',
            'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.logging.entryLimit',
            'configuration_value' => '{"_value": "10000000"}',
            'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.logging.entryLifetimeSeconds',
            'configuration_value' => '{"_value": "2678400"}', // one month
            'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
