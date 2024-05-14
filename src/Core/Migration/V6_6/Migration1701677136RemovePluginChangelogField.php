<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1701677136RemovePluginChangelogField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1701677136;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->columnExists($connection, 'plugin_translation', 'changelog')) {
            $connection->executeStatement('ALTER TABLE `plugin_translation` DROP COLUMN `changelog`');
        }
    }
}
