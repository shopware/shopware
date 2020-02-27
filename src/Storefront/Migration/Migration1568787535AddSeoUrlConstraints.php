<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1568787535AddSeoUrlConstraints extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1568787535;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `seo_url`
            MODIFY COLUMN `is_canonical` TINYINT(1) NULL
        ');

        $connection->executeUpdate('UPDATE seo_url SET is_canonical = NULL WHERE is_canonical = 0');
        $connection->executeUpdate('DELETE FROM seo_url WHERE is_valid = 0');

        $connection->executeUpdate('
            ALTER TABLE `seo_url`
            ADD CONSTRAINT `uniq.seo_url.seo_path_info` UNIQUE (`language_id`, `sales_channel_id`, `seo_path_info`),
            ADD CONSTRAINT `uniq.seo_url.foreign_key` UNIQUE (`language_id`, `sales_channel_id`, `foreign_key`, `route_name`, `is_canonical`);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
