<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1558082916AddBreadcrumb extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558082916;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `category_translation` ADD `breadcrumb` json NULL AFTER `name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
