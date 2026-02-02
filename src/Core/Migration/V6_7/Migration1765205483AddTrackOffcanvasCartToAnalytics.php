<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765205483AddTrackOffcanvasCartToAnalytics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765205483;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel_analytics', 'track_offcanvas_cart')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_analytics`
            ADD COLUMN `track_offcanvas_cart` TINYINT(1) NOT NULL DEFAULT 0
        ');
    }
}
