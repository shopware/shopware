<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1680789830AddCustomFieldsAwareToCustomEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1680789830;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'custom_entity', 'custom_fields_aware')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` ADD `custom_fields_aware` TINYINT(1) DEFAULT 0;');
            $connection->executeStatement('ALTER TABLE `custom_entity` ADD `label_property` VARCHAR(255) NULL;');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
