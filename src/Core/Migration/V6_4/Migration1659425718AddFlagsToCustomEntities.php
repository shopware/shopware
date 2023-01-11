<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package content
 *
 * @internal
 */
class Migration1659425718AddFlagsToCustomEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659425718;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM `custom_entity`'), 'Field');

        if (!\in_array('flags', $columns, true)) {
            $connection->executeStatement('ALTER TABLE `custom_entity` ADD `flags` JSON NULL;');
        }

        if (!\in_array('flag_config', $columns, true)) {
            $connection->executeStatement('ALTER TABLE `custom_entity` ADD `flag_config` JSON NULL;');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
