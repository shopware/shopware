<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234586User extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234586;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `user` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `last_login` datetime(3) DEFAULT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `email` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `active` tinyint(1) NOT NULL DEFAULT \'0\',
              `failed_logins` int(11) NOT NULL DEFAULT \'0\',
              `locked_until` datetime(3) DEFAULT NULL,
              `locale_id` binary(16) NOT NULL,
              `locale_version_id` binary(16) NOT NULL,
              `locale_tenant_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `tenant_id`),
              CONSTRAINT `fk_user.locale_id` FOREIGN KEY (`locale_id`, `locale_version_id`, `locale_tenant_id`) REFERENCES `locale` (`id`, `version_id`, `tenant_id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
