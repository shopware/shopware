<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549448593RemoveObjectField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549448593;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `discount_surcharge` MODIFY COLUMN `filter_rule` JSON NULL');
        $connection->exec('ALTER TABLE `rule` DROP COLUMN `payload`');
        $connection->exec('ALTER TABLE `rule` ADD COLUMN `payload` LONGBLOB NULL AFTER `priority`');
        $connection->exec('ALTER TABLE `media` MODIFY COLUMN `meta_data` LONGBLOB NULL, MODIFY COLUMN `media_type` LONGBLOB NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('ALTER TABLE `discount_surcharge` DROP COLUMN `filter_rule`');
    }
}
