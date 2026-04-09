<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1756068709FixCustomerAddressFirstNameLength extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1756068709;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer_address`
            MODIFY COLUMN `first_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL
        ');
    }
}
