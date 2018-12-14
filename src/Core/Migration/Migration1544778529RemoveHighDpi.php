<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544778529RemoveHighDpi extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1544778529;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media_thumbnail`
            MODIFY COLUMN `highDpi` TINYINT(1) DEFAULT \'0\';
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `media_thumbnail`
            DROP COLUMN `highDpi`;
        ');
    }
}
