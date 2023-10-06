<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1670854818RemoveEventActionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1670854818;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `event_action_sales_channel`');
        $connection->executeStatement('DROP TABLE IF EXISTS `event_action_rule`');
        $connection->executeStatement('DROP TABLE IF EXISTS `event_action`');
    }
}
