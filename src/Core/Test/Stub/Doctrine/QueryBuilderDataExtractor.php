<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Join;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * This class is created to have a quick working solution for BC breaks in DAL QueryBuilder API.
 * It's usage is strongly discouraged, as it may break with future DBAL changes.
 *
 * It should be removed as soon as no tests are using it anymore (see NEXT-40457).
 *
 * @internal
 */
#[Package('framework')]
class QueryBuilderDataExtractor
{
    /**
     * @return array<string>
     */
    public static function getSelect(QueryBuilder $queryBuilder): array
    {
        $selectProperty = self::getReflectionProperty($queryBuilder, 'select');
        $select = $selectProperty->getValue($queryBuilder);
        \assert(\is_array($select));

        return $select;
    }

    /**
     * @return array<string>
     */
    public static function getFrom(QueryBuilder $queryBuilder): array
    {
        $fromProperty = self::getReflectionProperty($queryBuilder, 'from');
        $from = $fromProperty->getValue($queryBuilder);
        \assert(\is_array($from));
        $result = [];
        foreach ($from as $fromObject) {
            \assert($fromObject instanceof \Doctrine\DBAL\Query\From);
            $result[] = $fromObject->table;
        }

        return $result;
    }

    public static function getWhere(QueryBuilder $queryBuilder): string|CompositeExpression|null
    {
        $whereProperty = self::getReflectionProperty($queryBuilder, 'where');

        $where = $whereProperty->getValue($queryBuilder);
        // assert that $where is string|CompositeExpression|null
        \assert($where instanceof CompositeExpression || $where === null || \is_string($where));

        return $where;
    }

    /**
     * @return array<string, array<array{type: string, table: string, alias: string, condition: string}>>
     */
    public static function getJoin(QueryBuilder $queryBuilder): array
    {
        $whereProperty = self::getReflectionProperty($queryBuilder, 'join');

        $joins = $whereProperty->getValue($queryBuilder);
        \assert(\is_array($joins));
        $result = [];
        foreach ($joins as $name => $joinClauses) {
            \assert(\is_string($name));
            \assert(\is_array($joinClauses));

            $result[$name] = [];
            foreach ($joinClauses as $joinClause) {
                \assert($joinClause instanceof Join);
                $result[$name][] = [
                    'type' => $joinClause->type,
                    'table' => $joinClause->table,
                    'alias' => $joinClause->alias,
                    'condition' => $joinClause->condition ?? '',
                ];
            }
        }

        return $result;
    }

    private static function getReflectionProperty(object $object, string $propertyName): \ReflectionProperty
    {
        // the property can be declared in the parent class, so we need to get the property from the parent class
        $reflection = new \ReflectionClass($object);
        while ($reflection !== false) {
            if ($reflection->hasProperty($propertyName)) {
                return $reflection->getProperty($propertyName);
            }
            $reflection = $reflection->getParentClass();
        }

        throw new \Exception("Property $propertyName not found in class " . $object::class);
    }
}
