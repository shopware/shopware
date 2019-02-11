<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549630974AddPluginComposerName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549630974;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            ALTER TABLE `plugin`
            ADD COLUMN `composer_name` VARCHAR(255) NULL AFTER `name`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
