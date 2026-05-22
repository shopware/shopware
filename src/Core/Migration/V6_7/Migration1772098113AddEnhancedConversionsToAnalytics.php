<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1772098113AddEnhancedConversionsToAnalytics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772098113;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel_analytics', 'enhanced_conversions')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_analytics`
            ADD COLUMN `enhanced_conversions` TINYINT(1) NOT NULL DEFAULT 0
        ');
    }
}
