<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1779600001UcpSigningKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_signing_key` (
                `id`                         BINARY(16)   NOT NULL,
                `sales_channel_id`           BINARY(16)   NOT NULL,
                `kid`                        VARCHAR(64)  NOT NULL,
                `algorithm`                  VARCHAR(16)  NOT NULL DEFAULT 'ES256',
                `public_jwk`                 JSON         NOT NULL,
                `private_key_pem_encrypted`  BLOB         NOT NULL,
                `status`                     VARCHAR(16)  NOT NULL DEFAULT 'active',
                `activated_at`               DATETIME(3)  NULL,
                `retiring_at`                DATETIME(3)  NULL,
                `created_at`                 DATETIME(3)  NOT NULL,
                `updated_at`                 DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_signing_key.sc_kid` (`sales_channel_id`, `kid`),
                KEY `idx.ucp_signing_key.status` (`sales_channel_id`, `status`),
                CONSTRAINT `json.ucp_signing_key.public_jwk` CHECK (JSON_VALID(`public_jwk`)),
                CONSTRAINT `fk.ucp_signing_key.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
