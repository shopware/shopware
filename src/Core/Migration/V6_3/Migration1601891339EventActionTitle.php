<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1601891339EventActionTitle extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601891339;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `event_action` ADD `title` varchar(500) NULL AFTER `id`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
