<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Stores `buyer.consent` snapshots so the business can:
 *   - persist consent decisions across multiple UCP checkout update calls
 *   - echo the canonical snapshot on subsequent responses
 *   - audit which buyer granted which consent at order-placement time
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600012UcpBuyerConsent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600012;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ucp_buyer_consent` (
                `id`               BINARY(16)   NOT NULL,
                `sales_channel_id` BINARY(16)   NOT NULL,
                `checkout_id`      VARCHAR(190) NOT NULL,
                `consent_json`     JSON         NOT NULL,
                `created_at`       DATETIME(3)  NOT NULL,
                `updated_at`       DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ucp_buyer_consent.checkout_id` (`checkout_id`),
                KEY `idx.ucp_buyer_consent.sales_channel_id` (`sales_channel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
