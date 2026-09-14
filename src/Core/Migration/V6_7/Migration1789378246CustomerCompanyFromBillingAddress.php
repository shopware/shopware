<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789378246CustomerCompanyFromBillingAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1789378246;
    }

    /**
     * The Administration wrote the company of a commercial account to its billing address only, while the
     * account now carries it itself
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `customer`
            INNER JOIN `customer_address` ON `customer_address`.`id` = `customer`.`default_billing_address_id`
            SET `customer`.`company` = `customer_address`.`company`
            WHERE `customer`.`account_type` = :business
                AND (`customer`.`company` IS NULL OR TRIM(`customer`.`company`) = \'\')
                AND `customer_address`.`company` IS NOT NULL
                AND TRIM(`customer_address`.`company`) <> \'\'
        ', ['business' => 'business']);
    }
}
