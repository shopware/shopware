<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1598280548NullableSeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1598280548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `seo_url_template` MODIFY `template` VARCHAR(750) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
