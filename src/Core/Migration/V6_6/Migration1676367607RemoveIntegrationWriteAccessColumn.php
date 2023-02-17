<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1676367607RemoveIntegrationWriteAccessColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1676367607;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'integration', 'write_access')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `integration` DROP COLUMN `write_access`
        ');
    }
}
