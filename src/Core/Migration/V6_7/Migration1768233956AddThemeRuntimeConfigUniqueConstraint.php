<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1768233956AddThemeRuntimeConfigUniqueConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768233956;
    }

    public function update(Connection $connection): void
    {
        $existingIndexes = $connection->createSchemaManager()->listTableIndexes('theme_runtime_config');
        if (isset($existingIndexes['uidx.technical_name'])) {
            return;
        }

        // Remove duplicate entries, keeping only the one with the newest updated_at
        $connection->executeStatement(<<<'SQL'
            DELETE t1 FROM `theme_runtime_config` t1
            INNER JOIN `theme_runtime_config` t2
            WHERE t1.`technical_name` = t2.`technical_name`
              AND t1.`technical_name` IS NOT NULL
              AND t1.`updated_at` < t2.`updated_at`
        SQL);

        // Drop existing non-unique index
        $this->dropIndexIfExists($connection, 'theme_runtime_config', 'idx.technical_name');

        // Add unique constraint
        $connection->executeStatement('ALTER TABLE `theme_runtime_config` ADD UNIQUE INDEX `uidx.technical_name` (`technical_name`)');
    }
}
