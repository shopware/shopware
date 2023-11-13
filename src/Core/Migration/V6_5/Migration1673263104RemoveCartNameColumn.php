<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1673263104RemoveCartNameColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673263104;
    }

    public function update(Connection $connection): void
    {
        $isCartNameNullable = <<<SQL
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_schema = ?
            AND table_name = 'cart'
            AND column_name = 'name';
        SQL;

        if ($connection->fetchOne($isCartNameNullable, [$connection->getDatabase()]) === 'NO') {
            $connection->executeStatement(
                'ALTER TABLE `cart` CHANGE `name` `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($connection->fetchOne('SHOW COLUMNS FROM `cart` LIKE \'name\'') === 'name') {
            $connection->executeStatement('ALTER TABLE `cart` DROP COLUMN `name`');
        }
    }
}
