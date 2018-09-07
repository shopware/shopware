<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536742910AddHasFileToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536742910;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media`
              ADD COLUMN `has_file` tinyint(1) NOT NULL DEFAULT 0
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
