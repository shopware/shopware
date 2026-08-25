<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1787635217AddVatIdCountryToCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787635217;
    }

    public function update(Connection $connection): void
    {
        $columnAdded = $this->addColumn($connection, 'customer', 'vat_id_country_id', 'BINARY(16)');

        if ($columnAdded) {
            $connection->executeStatement(
                'ALTER TABLE `customer`
                 ADD CONSTRAINT `fk.customer.vat_id_country_id` FOREIGN KEY (`vat_id_country_id`)
                 REFERENCES `country` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }
}
