<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1792138257AddMaintenancePasswordToSalesChannel extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1792138257;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel', 'maintenance_password', 'VARCHAR(255)');
    }
}
