<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1587107185AddToSAcceptanceToGoogleShoppingAccount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587107185;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `google_shopping_account` ADD `tos_accepted_at` DATETIME(3) DEFAULT NULL AFTER `credential`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
