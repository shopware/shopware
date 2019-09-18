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
        $connection->executeQuery('
          ALTER TABLE `seo_url`
          MODIFY COLUMN `is_canonical` TINYINT(1) NULL,
          ADD CONSTRAINT `uniq.seo_url.seo_path_info` UNIQUE (`language_id`, `sales_channel_id`, `seo_path_info`),
          ADD CONSTRAINT `uniq.seo_url.foreign_key` UNIQUE (`language_id`, `sales_channel_id`, `foreign_key`, `route_name`, `is_canonical`);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
