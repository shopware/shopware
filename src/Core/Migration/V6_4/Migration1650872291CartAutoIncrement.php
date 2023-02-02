<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1650872291CartAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650872291;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
