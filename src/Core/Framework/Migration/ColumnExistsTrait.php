<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Util\UtilException;

trait ColumnExistsTrait
{
    /**
     * @deprecated tag:v6.8.0 - reason:exception-change - Will throw {@see UtilException} instead of {@see TableNotFoundException}
     *
     * @param non-empty-string $table
     */
    protected function columnExists(Connection $connection, string $table, string $column): bool
    {
        if (Feature::isActive('v6.8.0.0')) {
            return TableHelper::columnExists($connection, $table, $column);
        }

        return (bool) $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );
    }
}
