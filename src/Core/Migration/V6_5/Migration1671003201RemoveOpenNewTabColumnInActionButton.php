<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package core
 *
 * @internal
 */
class Migration1671003201RemoveOpenNewTabColumnInActionButton extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1671003201;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `app_action_button` DROP COLUMN `open_new_tab`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
