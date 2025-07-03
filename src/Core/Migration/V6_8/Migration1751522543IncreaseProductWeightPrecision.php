<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1751522543IncreaseProductWeightPrecision extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1751522543;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            ALTER TABLE `product`
            MODIFY COLUMN `weight` DECIMAL(15,6) unsigned NULL;
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
} 