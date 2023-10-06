<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('content')]
class Migration1666689977AddPluginIdToCustomEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1666689977;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'custom_entity', 'plugin_id')) {
            $connection->executeStatement('ALTER TABLE `custom_entity`
                ADD `plugin_id` BINARY(16) NULL,
                ADD CONSTRAINT `fk.custom_entity.plugin_id`
                    FOREIGN KEY (`plugin_id`)
                    REFERENCES `plugin` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
