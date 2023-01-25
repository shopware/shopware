<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1583844433AddRefreshTokenTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583844433;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `refresh_token` (
              `id`                  BINARY(16)                              NOT NULL,
              `user_id`             BINARY(16)                              NOT NULL,
              `token_id`            VARCHAR(80)                             NOT NULL,
              `issued_at`           DATETIME(3)                             NOT NULL,
              `expires_at`          DATETIME(3)                             NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.token_id` (token_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
