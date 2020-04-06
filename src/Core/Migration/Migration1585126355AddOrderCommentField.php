<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1585126355AddOrderCommentField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585126355;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `order`
            ADD COLUMN `customer_comment` LONGTEXT COLLATE utf8mb4_unicode_ci NULL AFTER `campaign_code`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
