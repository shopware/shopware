<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1559050088Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559050088;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `promotion` ADD `customer_restriction` TINYINT(1) NOT NULL DEFAULT 0;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
