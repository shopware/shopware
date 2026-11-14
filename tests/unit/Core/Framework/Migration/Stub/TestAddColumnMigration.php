<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Stub;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\AddColumnTrait;

/**
 * @internal
 */
class TestAddColumnMigration
{
    use AddColumnTrait;

    /**
     * @param non-empty-string $table
     * @param non-empty-string $column
     */
    public function callAddColumn(
        Connection $connection,
        string $table,
        string $column,
        string $type,
        bool $nullable = true,
        string $default = 'NULL'
    ): bool {
        return $this->addColumn($connection, $table, $column, $type, $nullable, $default);
    }
}
