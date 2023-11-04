<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1570187167AddedAppConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570187167;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `app_config` (
              `key` varchar(50) NOT NULL,
              `value` LONGTEXT NOT NULL,
               PRIMARY KEY (`key`)
            );
        ');

        $connection->insert('app_config', [
            '`key`' => 'cache-id',
            '`value`' => Uuid::randomHex(),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
