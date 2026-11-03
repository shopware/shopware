<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1793709317AddOwnerTypeToWebhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793709317;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'webhook', 'owner_type', 'VARCHAR(16)');

        $connection->executeStatement(<<<'SQL'
            UPDATE webhook SET owner_type = CASE
                WHEN app_id IS NOT NULL THEN 'app'
                WHEN owner_user_id IS NOT NULL THEN 'user'
                WHEN owner_integration_id IS NOT NULL THEN 'integration'
            END
            WHERE owner_type IS NULL
        SQL);
    }
}
