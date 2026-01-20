<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1664894872AddDelayableColumnToAppFlowActionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664894872;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'app_flow_action', 'delayable')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `app_flow_action` ADD COLUMN `delayable` BOOL NOT NULL DEFAULT FALSE AFTER `url`
        ');
    }
}
