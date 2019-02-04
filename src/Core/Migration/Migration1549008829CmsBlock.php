<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549008829CmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549008829;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `cms_block` (
              `id` binary(16) NOT NULL,
              `cms_page_id` binary(16) NOT NULL,
              `position` int(11) NOT NULL,
              `type` varchar(255) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.cms_block.cms_page_id` FOREIGN KEY (`cms_page_id`) REFERENCES `cms_page` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
