<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1572264837AddCacheId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572264837;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM app_config');

        try {
            $connection->executeStatement('ALTER TABLE app_config ADD PRIMARY KEY (`key`)');
        } catch (Exception) {
            // PK already exists
        }

        $connection->executeStatement(
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
