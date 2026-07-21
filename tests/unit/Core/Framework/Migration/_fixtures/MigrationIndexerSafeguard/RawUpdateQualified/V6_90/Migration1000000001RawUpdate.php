<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\RawUpdateQualified\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration1000000001RawUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `fixture_indexed_table` SET `fixture_indexed_table`.`watched_column` = 1');
    }
}
