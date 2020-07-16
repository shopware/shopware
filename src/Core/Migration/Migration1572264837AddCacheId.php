<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1572264837AddCacheId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572264837;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('DELETE FROM app_config');

        try {
            $connection->exec('ALTER TABLE app_config ADD PRIMARY KEY (`key`)');
        } catch (DBALException $e) {
            // PK already exists
        }

        $connection->executeUpdate(
            '
            INSERT IGNORE INTO app_config (`key`, `value`)
            VALUES (?, ?)',
            ['cache-id', Uuid::randomHex()]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
