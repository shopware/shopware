<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Shopware\Core\Framework\Log\Package;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 * @package storefront
 */
#[Package('storefront')]
class Migration1565640175RemoveSalesChannelTheme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1565640175;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_theme`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
