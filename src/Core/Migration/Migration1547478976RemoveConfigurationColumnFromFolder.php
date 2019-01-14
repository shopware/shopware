<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1547478976RemoveConfigurationColumnFromFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1547478976;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `media_folder`
            DROP COLUMN `configuration`;
        ');
    }
}
