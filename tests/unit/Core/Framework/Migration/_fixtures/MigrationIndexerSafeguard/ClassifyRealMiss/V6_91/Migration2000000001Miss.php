<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\ClassifyRealMiss\V6_91;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * @internal
 */
final class Migration2000000001Miss extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 2000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->insert('fixture_indexed_table', ['id' => 'x']);
    }
}
