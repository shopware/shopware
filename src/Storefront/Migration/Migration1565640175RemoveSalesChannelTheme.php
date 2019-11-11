<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1565640175RemoveSalesChannelTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565640175;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS `sales_channel_theme`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
