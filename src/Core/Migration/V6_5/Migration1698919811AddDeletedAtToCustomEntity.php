<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1698919811AddDeletedAtToCustomEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1698919811;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'custom_entity', 'deleted_at')) {
            $connection->executeStatement(
                'ALTER TABLE `custom_entity` ADD `deleted_at` DATETIME(3) NULL;'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
