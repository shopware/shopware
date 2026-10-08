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
        if (!$this->addColumn($connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis', 'VARCHAR(255)', false, '\'gross\'')) {
            return;
        }

        $connection->executeStatement('UPDATE `customer_group` SET `price_basis` = \'net\' WHERE `display_gross` = 0');
    }
}
