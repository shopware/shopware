<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1783598104AddProductGuaranteeMonths extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783598104;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product', 'guarantee_months')) {
            return;
        }

        $this->addColumn($connection, 'product', 'guarantee_months', 'INT');
    }
}
