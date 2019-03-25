<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553521641CleanUpConfigurationGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553521641;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `configuration_group`
            DROP COLUMN `filterable`,
            DROP COLUMN `comparable`,
            DROP COLUMN `position`
            ;'
        );
    }
}
