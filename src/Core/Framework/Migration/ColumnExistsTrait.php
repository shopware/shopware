<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

trait ColumnExistsTrait
{
    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }
}
