<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1794410950FixBusinessAccountTypeWithoutCompany extends MigrationStep
{
    private const CHUNK_SIZE = 1000;

    public function getCreationTimestamp(): int
    {
        return 1794410950;
    }

    public function update(Connection $connection): void
    {
        // Migration1676272000AddAccountTypeToCustomer classified customers as business based on the company of their
        // default billing address, without ensuring that `customer.company` is populated. Such records violate the
        // business account validation (company is mandatory) and break the B2B tax exemption, which requires
        // `customer.company`. Restore the previous classification based on `customer.company`.
        $sql = 'UPDATE `customer`
                SET `account_type` = :private
                WHERE `account_type` = :business
                    AND (`company` IS NULL OR TRIM(`company`) = \'\')
                ORDER BY `id`
                LIMIT :limit';

        do {
            $affectedRows = (int) $connection->executeStatement(
                $sql,
                [
                    'private' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
                    'business' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                    'limit' => self::CHUNK_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );
        } while ($affectedRows === self::CHUNK_SIZE);
    }
}
