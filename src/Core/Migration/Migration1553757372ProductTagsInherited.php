<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553757372ProductTagsInherited extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553757372;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE product ADD COLUMN tags BINARY(16) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
