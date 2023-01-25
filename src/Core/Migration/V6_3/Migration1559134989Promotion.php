<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1559134989Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559134989;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `promotion` ADD `use_individual_codes` TINYINT(1) NOT NULL DEFAULT 0;');
        $connection->executeStatement('ALTER TABLE `promotion` ADD `individual_code_pattern` VARCHAR(255) NULL UNIQUE;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
