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
class Migration1643366069AddSeoUrlUpdaterIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1643366069;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'seo_url', 'idx.delete_query')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.delete_query` ON seo_url (foreign_key, sales_channel_id);');
    }
}
