<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1648709176CartCompression extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1648709176;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'cart', 'compressed')) {
            $connection->executeStatement('ALTER TABLE `cart` ADD `compressed` tinyint(1) NOT NULL DEFAULT 0;');
        }

        // after adding the payload column, we may save carts as compressed serialized objects, there is no way of return at this point
        if (!$this->columnExists($connection, 'cart', 'payload')) {
            $connection->executeStatement('ALTER TABLE `cart` ADD `payload` LONGBLOB NULL;');
        }

        if (!$this->columnExists($connection, 'cart', 'cart')) {
            return;
        }

        do {
            $affectedRows = RetryableQuery::retryable($connection, static function () use ($connection): int {
                return (int) $connection->executeStatement(
                    'UPDATE cart SET `payload` = `cart` WHERE `payload` IS NULL AND `cart` IS NOT NULL LIMIT :limit',
                    ['limit' => self::UPDATE_LIMIT],
                    ['limit' => ParameterType::INTEGER]
                );
            });
        } while ($affectedRows === self::UPDATE_LIMIT);

        $connection->executeStatement('ALTER TABLE `cart` DROP COLUMN `cart`;');
    }
}
