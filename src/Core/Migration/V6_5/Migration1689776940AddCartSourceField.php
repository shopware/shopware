<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1689776940AddCartSourceField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689776940;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'order', 'source')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `order`
            ADD COLUMN `source` VARCHAR(255) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
