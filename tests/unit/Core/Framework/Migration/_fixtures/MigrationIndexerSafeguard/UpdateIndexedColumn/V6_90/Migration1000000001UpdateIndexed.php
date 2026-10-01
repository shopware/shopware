<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\UpdateIndexedColumn\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration1000000001UpdateIndexed extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->update('fixture_indexed_table', ['watched_column' => 'x', 'other' => 'y'], ['id' => 'z']);
    }
}
