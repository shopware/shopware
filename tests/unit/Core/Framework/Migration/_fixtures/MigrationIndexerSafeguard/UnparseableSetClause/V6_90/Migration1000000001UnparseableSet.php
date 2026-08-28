<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\UnparseableSetClause\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * The raw UPDATE uses a user-variable assignment (`@row := ...`) as the SET
 * body; extractSetColumns cannot parse any column identifier and falls back
 * to null columns, so the engine flags the write conservatively.
 *
 * @internal
 */
final class Migration1000000001UnparseableSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE fixture_indexed_table SET @row := @row + 1');
    }
}
