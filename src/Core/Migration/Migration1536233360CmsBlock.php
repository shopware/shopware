<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233360CmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233360;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `cms_block` (
              `id` BINARY(16) NOT NULL,
              `cms_page_id` BINARY(16) NOT NULL,
              `position` INT(11) NOT NULL,
              `type` VARCHAR(255) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.cms_block.cms_page_id` FOREIGN KEY (`cms_page_id`)
                REFERENCES `cms_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.cms_block.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
