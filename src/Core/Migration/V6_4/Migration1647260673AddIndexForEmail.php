<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1647260673AddIndexForEmail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1647260673;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'customer', 'idx.email')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.email` ON `customer` (`email`)');
    }
}
