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
class Migration1639139581AddPriorityToPromotions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639139581;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'promotion', 'priority')) {
            return;
        }

        $sql = <<<'SQL'
ALTER TABLE `promotion` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 1 AFTER `max_redemptions_per_customer`;
SQL;

        $connection->executeStatement($sql);
    }
}
