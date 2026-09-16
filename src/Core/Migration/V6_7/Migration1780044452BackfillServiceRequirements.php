<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1780044452BackfillServiceRequirements extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780044452;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE `app`
            SET `source_config` = JSON_ARRAY_APPEND(`source_config`, '$.requirements', 'services_enabled')
            WHERE `self_managed` = 1
            AND `source_config` IS NOT NULL
            AND JSON_TYPE(JSON_EXTRACT(`source_config`, '$.requirements')) = 'ARRAY'
            AND JSON_CONTAINS(JSON_EXTRACT(`source_config`, '$.requirements'), '"service_consent"') = 1
            AND JSON_CONTAINS(JSON_EXTRACT(`source_config`, '$.requirements'), '"services_enabled"') = 0
            SQL);
    }
}
