<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1595149625AddVisibleOnDetailToPropertyGroupTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595149625;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `property_group_translation`
            ADD COLUMN `visible_on_detail` TINYINT(1) NOT NULL DEFAULT 1
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // TODO: Implement updateDestructive() method.
    }
}
