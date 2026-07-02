<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782975803CreateAdminAuthMfaChallenge extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782975803;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_auth_mfa_challenge` (
                `id`                 BINARY(16)   NOT NULL,
                `user_id`            BINARY(16)   NOT NULL,
                `pending_jti`        VARCHAR(100) NOT NULL,
                `webauthn_challenge` VARBINARY(255) NULL,
                `allowed_methods`    JSON         NOT NULL,
                `attempts`           INT          NOT NULL DEFAULT 0,
                `consumed`           TINYINT(1)   NOT NULL DEFAULT 0,
                `expires_at`         DATETIME(3)  NOT NULL,
                `created_at`         DATETIME(3)  NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.admin_auth_mfa_challenge.pending_jti` (`pending_jti`),
                KEY `idx.admin_auth_mfa_challenge.expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
