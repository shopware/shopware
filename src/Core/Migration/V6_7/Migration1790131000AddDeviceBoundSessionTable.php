<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1790131000AddDeviceBoundSessionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790131000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `device_bound_session` (
              `id`                   BINARY(16)    NOT NULL,
              `context_token`        VARCHAR(255)  NOT NULL,
              `public_key`           JSON          NOT NULL,
              `cookie_hash`          VARCHAR(64)   NOT NULL,
              `previous_cookie_hash` VARCHAR(64)   NULL,
              `challenge`            VARCHAR(255)  NULL,
              `refreshed_at`         DATETIME(3)   NOT NULL,
              `expires_at`           DATETIME(3)   NOT NULL,
              `created_at`           DATETIME(3)   NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.device_bound_session.context_token` (`context_token`),
              INDEX `idx.device_bound_session.expires_at` (`expires_at`),
              CONSTRAINT `json.device_bound_session.public_key` CHECK (JSON_VALID(`public_key`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
