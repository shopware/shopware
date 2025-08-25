<?php declare(strict_types=1);

namespace Shopware\Administration\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1737472122TokenUser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737472122;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS oauth_user (
                `id` BINARY(16) UNIQUE NOT NULL,
                `user_id` BINARY(16) UNIQUE NOT NULL,
                `user_sub` VARCHAR(255) UNIQUE NOT NULL,
                `token` JSON DEFAULT NULL,
                `expiry` DATETIME NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.oauth_user.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                KEY `idx.oauth_user.user_sub` (`user_sub`)
            )
        ');
    }
}
