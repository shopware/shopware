<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553675500CleanUpCurrency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553675500;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `currency`
            DROP COLUMN `is_default`,
            DROP COLUMN `placed_in_front`
            ;'
        );
    }
}
