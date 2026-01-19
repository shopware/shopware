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
        if ($this->indexExists($connection, 'theme_runtime_config', 'uidx.technical_name')) {
            return;
        }

        // Remove duplicate entries, keeping only the one with the newest updated_at
        $connection->executeStatement(<<<'SQL'
            DELETE t
            FROM theme_runtime_config t
            JOIN (
                SELECT theme_id
                FROM (
                    SELECT
                        theme_id,
                        ROW_NUMBER() OVER (
                            PARTITION BY technical_name
                            ORDER BY updated_at DESC, theme_id DESC
                        ) AS rn
                    FROM theme_runtime_config
                    WHERE technical_name IS NOT NULL
                ) ranked
                WHERE rn > 1
            ) duplicates ON duplicates.theme_id = t.theme_id
        SQL);

        // Drop existing non-unique index
        $this->dropIndexIfExists($connection, 'theme_runtime_config', 'idx.technical_name');

        // Add unique constraint
        $connection->executeStatement('ALTER TABLE `theme_runtime_config` ADD UNIQUE INDEX `uidx.technical_name` (`technical_name`)');
    }
}
