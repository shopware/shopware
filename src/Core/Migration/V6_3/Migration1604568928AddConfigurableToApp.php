<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1604568928AddConfigurableToApp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604568928;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `app`
            ADD `configurable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
