<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544795529RemoveTypeFromRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1544795529;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `rule`
            DROP COLUMN `type`;
        ');
    }
}
