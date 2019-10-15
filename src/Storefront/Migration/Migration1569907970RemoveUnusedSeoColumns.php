<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1569907970RemoveUnusedSeoColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1569907970;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `seo_url`
            MODIFY COLUMN `is_valid` TINYINT(1) NULL,
            MODIFY COLUMN `auto_increment` BIGINT unsigned NULL,
            DROP INDEX `idx.path_info`,
            DROP INDEX `idx.seo_path_info`,
            ADD INDEX `idx.path_info` (`language_id`,`sales_channel_id`, `is_canonical`, `path_info`)
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `seo_url`
            DROP COLUMN `is_valid`,
            DROP COLUMN `auto_increment`
        ');
    }
}
