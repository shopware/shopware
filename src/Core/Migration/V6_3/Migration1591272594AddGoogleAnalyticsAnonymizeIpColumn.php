<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1591272594AddGoogleAnalyticsAnonymizeIpColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1591272594;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE sales_channel_analytics
            ADD COLUMN anonymize_ip TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
