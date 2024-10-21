<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Log\Package;

/**
 * @description This class is used to build a select part of a SQL.
 *
 * @final
 */
#[Package('core')]
class SqlHelper
{
    /**
     * @description This function is used to build the json_object of the select part of a query.
     *
     * @param array<string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'product.id', 'name' => 'product.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'product');
     * // $select = "JSON_OBJECT('id', product.id, 'name', product.name) as product";
     */
    public static function object(array $fields, string $alias): string
    {
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', $columnAlias, $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = 'JSON_OBJECT(%s) as %s';

        return \sprintf($sql, $columnSelectSql, $alias);
    }

    /**
     * @description This function is used to build the json_array of the select part of a query.
     *
     * @param array<int|string, string> $fields The key is the alias of the column, the value is the column itself
     *
     * @example
     *
     * $fields = [ 'id' => 'tags.id', 'name' => 'tags.name' ];
     * $select = EntityDefinitionQueryHelper::object($fields, 'tags');
     * // $select = "CONCAT('[', GROUP_CONCAT(DISTINCT JSON_OBJECT('id', tags.id, 'name', tags.name)), ']') as tags";
     */
    public static function objectArray(array $fields, string $alias): string
    {
        $columnSelectSql = '';

        foreach ($fields as $columnAlias => $column) {
            $columnSelectSql .= \sprintf('\'%s\', %s,', $columnAlias, $column);
        }

        $columnSelectSql = rtrim($columnSelectSql, ',');

        $sql = <<<EOF
CONCAT(
    '[',
         GROUP_CONCAT(DISTINCT
             JSON_OBJECT(
                %s
             )
         ),
    ']'
) as %s
EOF;

        return \sprintf($sql, $columnSelectSql, $alias);
    }
}
