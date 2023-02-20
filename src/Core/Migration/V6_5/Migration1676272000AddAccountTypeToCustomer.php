<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('customer-order')]
class Migration1676272000AddAccountTypeToCustomer extends MigrationStep
{
    private const CHUNK_SIZE = 5000;

    public function getCreationTimestamp(): int
    {
        return 1676272000;
    }

    public function update(Connection $connection): void
    {
        $isAdded = $this->columnExists($connection, 'customer', 'account_type');
        if (!$isAdded) {
            $connection->executeStatement('
                ALTER TABLE `customer`
                ADD COLUMN `account_type` VARCHAR(255) NOT NULL DEFAULT \'private\' AFTER `bound_sales_channel_id`
            ');
        }

        $this->massUpdateAccountType($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function massUpdateAccountType(Connection $connection): void
    {
        $sql = 'UPDATE customer
                SET account_type = :type
                WHERE account_type != :type AND customer.vat_ids IS NOT NULL
        ';

        $connection->executeStatement(
            $sql,
            [
                'type' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            ]
        );

        $sql = 'UPDATE `customer`
                SET
                    `account_type` = :type
                WHERE
                    `account_type` != :type AND EXISTS (
                        SELECT *
                        FROM
                            `customer_address`
                        WHERE
                            `customer`.`default_billing_address_id` = `customer_address`.`id`
                                AND `customer_address`.`company` IS NOT NULL
                    )
                ORDER BY `customer`.`id`
                LIMIT :limit
        ';

        $affectedRow = self::CHUNK_SIZE;
        while ($affectedRow === self::CHUNK_SIZE) {
            $affectedRow = (int) $connection->executeStatement(
                $sql,
                [
                    'type' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                    'limit' => self::CHUNK_SIZE,
                ],
                [
                    'limit' => \PDO::PARAM_INT,
                ]
            );
        }
    }
}
