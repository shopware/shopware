<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1648709176CartCompression extends MigrationStep
{
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
            $connection->executeStatement('ALTER TABLE `cart` ADD `compressed` tinyint(1) NOT NULL DEFAULT \'0\';');
        }

        // after adding the payload column, we may save carts as compressed serialized objects, there is no way of return at this point
        if (!$this->columnExists($connection, 'cart', 'payload')) {
            $connection->executeStatement('ALTER TABLE `cart` ADD `payload` LONGBLOB NULL;');
        }

        if (!$this->columnExists($connection, 'cart', 'cart')) {
            return;
        }

        while ($token = $connection->fetchOne('SELECT token FROM cart WHERE `payload` IS NULL AND `cart` IS NOT NULL')) {
            RetryableQuery::retryable($connection, static function () use ($connection, $token): void {
                $connection->executeStatement('UPDATE cart SET `payload` = `cart`, `compressed` = 0 WHERE token = :token', ['token' => $token]);
            });
        }

        $connection->executeStatement('ALTER TABLE `cart` DROP COLUMN `cart`;');
    }
}
