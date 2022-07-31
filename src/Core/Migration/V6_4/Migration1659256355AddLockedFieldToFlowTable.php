<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1659256355AddLockedFieldToFlowTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1659256355;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM flow'), 'Field');

        if (\in_array('locked', $columns, true)) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `flow` ADD `locked` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `active`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
