<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549378377DeadMessage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549378377;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `dead_message` (
              `id` binary(16) NOT NULL,
              `original_message_class` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `serialized_original_message` longblob NULL,
              `handler_class` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `encrypted` tinyint(1) DEFAULT \'0\' NOT NULL,
              `error_count` int(11) NOT NULL,
              `next_execution_time` datetime(3) NOT NULL,
              `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_file` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `exception_line` int(11) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `min.error_count` CHECK (error_count >= 1)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
