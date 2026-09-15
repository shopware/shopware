<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1774895840AddPerformanceImprovedSeoUrlIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774895840;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'seo_url', 'idx.path_info') && TableHelper::indexSpansColumns($connection, 'seo_url', 'idx.path_info', ['path_info', 'is_canonical', 'sales_channel_id', 'language_id', 'seo_path_info'])) {
            return;
        }

        if (TableHelper::indexExists($connection, 'seo_url', 'idx.path_info')) {
            $connection->executeStatement('DROP INDEX `idx.path_info` ON `seo_url`');
        }

        $connection->executeStatement('
            CREATE INDEX `idx.path_info` ON `seo_url` (
                `path_info`(255),
                `is_canonical`,
                `sales_channel_id`,
                `language_id`,
                `seo_path_info`(255)
            )
        ');
    }
}
