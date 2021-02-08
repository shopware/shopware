<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1559134989Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559134989;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `promotion` ADD `use_individual_codes` TINYINT(1) NOT NULL DEFAULT 0;');
        $connection->executeUpdate('ALTER TABLE `promotion` ADD `individual_code_pattern` VARCHAR(255) NULL UNIQUE;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
