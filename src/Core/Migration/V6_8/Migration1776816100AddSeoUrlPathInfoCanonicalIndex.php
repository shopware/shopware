<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1776816100AddSeoUrlPathInfoCanonicalIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776816100;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'seo_url', 'idx.path_info.is_canonical')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.path_info.is_canonical` ON `seo_url` (`path_info`, `is_canonical`)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
