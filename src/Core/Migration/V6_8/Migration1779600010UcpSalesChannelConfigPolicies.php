<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Adds `signature_policy` and `idempotency_required` to
 * `ucp_sales_channel_config`. These are operator-controlled toggles for the
 * inbound RFC 9421 verifier and the Idempotency-Key requirement on UCP routes.
 *
 * @internal
 */
#[Package('framework')]
class Migration1779600010UcpSalesChannelConfigPolicies extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779600010;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative('SHOW COLUMNS FROM `ucp_sales_channel_config`');
        $names = array_column($columns, 'Field');

        // ALTER TABLE .. AFTER is disallowed in migrations to avoid implicit
        // temporary tables on large customer datasets; column position has no
        // functional impact here.
        if (!\in_array('signature_policy', $names, true)) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `ucp_sales_channel_config`
                ADD COLUMN `signature_policy` VARCHAR(16) NOT NULL DEFAULT 'strict'
            SQL);
        }

        if (!\in_array('idempotency_required', $names, true)) {
            $connection->executeStatement(<<<'SQL'
                ALTER TABLE `ucp_sales_channel_config`
                ADD COLUMN `idempotency_required` TINYINT(1) NOT NULL DEFAULT 1
            SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
