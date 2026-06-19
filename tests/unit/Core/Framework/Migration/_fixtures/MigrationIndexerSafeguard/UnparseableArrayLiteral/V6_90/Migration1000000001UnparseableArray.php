<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationIndexerSafeguard\UnparseableArrayLiteral\V6_90;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationIndexerSafeguardTest — regex-parsed, never executed.
 *
 * The $connection->update(...) call uses a variable-keyed array literal —
 * the outer regex still captures `[$column => $value]`, but extractArrayKeys
 * finds no `'key' =>` literal patterns and falls back to null columns, so
 * the engine cannot narrow by column allow-list and must flag the write
 * conservatively.
 *
 * @internal
 */
final class Migration1000000001UnparseableArray extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $column = 'id';
        $value = 'x';
        $connection->update('fixture_indexed_table', [$column => $value], ['id' => 1]);
    }
}
