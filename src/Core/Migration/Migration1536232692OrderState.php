<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232692OrderState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232692;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `order_state` (
              `id` binary(16) NOT NULL,
              `position` int(11) NOT NULL DEFAULT \'1\',
              `has_mail` tinyint(1) NOT NULL DEFAULT \'0\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
