<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

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
        $this->addColumn($connection, 'customer', 'vat_id_country_id', 'BINARY(16)');

        // Guarded on its own: a run that adds the column and then fails on the constraint is retried, and
        // `addColumn` returns false on that retry, so a guard on its result would skip the key forever
        if (!TableHelper::foreignKeyExists($connection, 'customer', 'fk.customer.vat_id_country_id')) {
            $connection->executeStatement(
                'ALTER TABLE `customer`
                 ADD CONSTRAINT `fk.customer.vat_id_country_id` FOREIGN KEY (`vat_id_country_id`)
                 REFERENCES `country` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }
}
