<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1565007156RemoveAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565007156;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product DROP COLUMN auto_increment');
        $connection->executeStatement('ALTER TABLE category DROP COLUMN auto_increment');
    }
}
