<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788947575AddOAuthAuthCodeTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788947575;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `oauth_auth_code` (
              `id`         BINARY(16)    NOT NULL,
              `code_id`    VARCHAR(80)   NOT NULL,
              `user_id`    BINARY(16)    NOT NULL,
              `client_id`  VARCHAR(255)  NOT NULL,
              `issued_at`  DATETIME(3)   NOT NULL,
              `expires_at` DATETIME(3)   NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.oauth_auth_code.code_id` (`code_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
