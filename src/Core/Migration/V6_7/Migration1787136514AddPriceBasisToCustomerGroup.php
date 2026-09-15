<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1787136514AddPriceBasisToCustomerGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787136514;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis')) {
            return;
        }

        $this->addColumn($connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis', 'VARCHAR(255)');
    }
}
