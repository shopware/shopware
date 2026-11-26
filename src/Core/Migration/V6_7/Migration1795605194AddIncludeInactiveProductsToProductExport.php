<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1795605194AddIncludeInactiveProductsToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795605194;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'include_inactive_products', 'TINYINT(1)', nullable: false, default: '0');
    }
}
