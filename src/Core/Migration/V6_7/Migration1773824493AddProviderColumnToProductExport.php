<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1773824493AddProviderColumnToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773824493;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'provider', 'VARCHAR(255)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
