<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1789378246CustomerCompanyFromBillingAddress extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1789378246;
    }

    public function update(Connection $connection): void
    {
        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `customer`.`id`
                FROM `customer`
                INNER JOIN `customer_address` ON `customer_address`.`id` = `customer`.`default_billing_address_id`
                WHERE `customer`.`account_type` = :business
                    AND (`customer`.`company` IS NULL OR TRIM(`customer`.`company`) = \'\')
                    AND `customer_address`.`company` IS NOT NULL
                    AND TRIM(`customer_address`.`company`) <> \'\'
                LIMIT :limit',
                ['business' => 'business', 'limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                break;
            }

            $connection->executeStatement(
                'UPDATE `customer`
                INNER JOIN `customer_address` ON `customer_address`.`id` = `customer`.`default_billing_address_id`
                SET `customer`.`company` = `customer_address`.`company`
                WHERE `customer`.`id` IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === self::UPDATE_LIMIT);
    }
}
