<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds the `is_external_storefront` flag to `sales_channel_domain`. SEO URLs for headless sales channels are
 * only generated for domains flagged as external storefront. Defaults to `0` (false).
 *
 * @internal
 */
#[Package('discovery')]
class Migration1784801509AddIsExternalStorefrontToSalesChannelDomain extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1784801509;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel_domain', 'is_external_storefront', 'TINYINT(1)', false, '0');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
