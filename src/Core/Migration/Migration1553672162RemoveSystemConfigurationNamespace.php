<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553672162RemoveSystemConfigurationNamespace extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553672162;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `system_config`
            DROP INDEX `uniq.configuration_key__namespace__sales_channel_id`,
            MODIFY COLUMN `namespace` VARCHAR(255) NULL,
            ADD CONSTRAINT `uniq.system_configuration.configuration_key`
                UNIQUE (`sales_channel_id`, `configuration_key`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeUpdate(sprintf('
            UPDATE system_config
            SET `configuration_value` =
                IF(JSON_VALID(`configuration_value`) AND (JSON_TYPE(`configuration_value`) = "OBJECT" OR JSON_TYPE(`configuration_value`) = "ARRAY"),
                    JSON_OBJECT("%s", JSON_MERGE("{}", `configuration_value`)),
                    JSON_OBJECT("%s", `configuration_value`)
                )',
            ConfigJsonField::STORAGE_KEY,
            ConfigJsonField::STORAGE_KEY
            )
        );

        $connection->exec('
            ALTER TABLE `system_config`
            DROP COLUMN `namespace`,
            MODIFY COLUMN `configuration_value` JSON NOT NULL,
            ADD CONSTRAINT `json.configuration_value` CHECK (JSON_VALID(`configuration_value`))
        ');
    }
}
