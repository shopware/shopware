<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1779600002UcpPlatformProfileCache extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_platform_profile_cache` (
                `id`                  BINARY(16)    NOT NULL,
                `profile_uri`         TEXT          NOT NULL,
                `profile_uri_hash`    VARCHAR(64)   NOT NULL,
                `profile_json`        JSON          NOT NULL,
                `etag`                VARCHAR(255)  NULL,
                `fetched_at`          DATETIME(3)   NOT NULL,
                `expires_at`          DATETIME(3)   NOT NULL,
                `verification_status` VARCHAR(16)   NOT NULL DEFAULT 'pending',
                `failure_count`       INT UNSIGNED  NOT NULL DEFAULT 0,
                `created_at`          DATETIME(3)   NOT NULL,
                `updated_at`          DATETIME(3)   NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_ppc.uri_hash` (`profile_uri_hash`),
                KEY `idx.ucp_ppc.expires_at` (`expires_at`),
                CONSTRAINT `json.ucp_ppc.profile_json` CHECK (JSON_VALID(`profile_json`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
