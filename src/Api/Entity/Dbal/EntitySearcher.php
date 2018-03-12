<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Parser\SqlQueryParser;
use Shopware\Api\Entity\Search\Query\ScoreQuery;
use Shopware\Context\Struct\ShopContext;

class EntitySearcher implements EntitySearcherInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function search(string $definition, Criteria $criteria, ShopContext $context): IdSearchResult
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = EntityDefinitionQueryHelper::getBaseQuery($this->connection, $definition, $context);

        if ($definition::getParentPropertyName()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
            EntityDefinitionQueryHelper::joinManyToOne($definition, $definition::getEntityName(), $parent, $query, $context);
        }

        //add id select, e.g. `product`.`id`;
        $query->addSelect(
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as array_key',
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as primary_key'
        );

        $fields = array_merge(
            $criteria->getSortingFields(),
            $criteria->getFilterFields(),
            $criteria->getPostFilterFields(),
            $criteria->getQueryFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionQueryHelper::joinField($fieldName, $definition, $table, $query, $context);
        }

        $this->addFilters($definition, $criteria, $query, $context);

        $this->addQueries($definition, $criteria, $query, $context);

        $this->addSortings($definition, $criteria, $query, $context);

        $this->addGroupBy($definition, $criteria, $query, $context);

        //add pagination
        if ($criteria->getOffset() >= 0) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() >= 0) {
            $query->setMaxResults($criteria->getLimit());
        }

        $this->addFetchCount($criteria, $query);

        //execute and fetch ids
        $data = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        if ($criteria->fetchCount() === Criteria::FETCH_COUNT_TOTAL) {
            $total = (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        } elseif ($criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $total = count($data) - $criteria->getLimit();
            $data = array_slice($data, 0, $criteria->getLimit());
        } else {
            $total = count($data);
        }

        $converted = [];
        foreach ($data as $key => $values) {
            $key = Uuid::fromBytes($key)->toString();
            $values['primary_key'] = $key;
            $converted[$key] = $values;
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    private function addQueries(string $definition, Criteria $criteria, QueryBuilder $query, ShopContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $queries = SqlQueryParser::parseRanking(
            $criteria->getQueries(),
            $definition,
            $definition::getEntityName(),
            $context
        );
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::REQUIRES_GROUP_BY);

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ')';
        $query->addSelect($select . ' as score');

        if (empty($criteria->getSortings())) {
            $query->addOrderBy('score', 'DESC');
        }

        $minScore = array_map(function (ScoreQuery $query) {
            return $query->getScore();
        }, $criteria->getQueries());

        $minScore = min($minScore);

        $query->andHaving('score >= :_minScore');
        $query->setParameter('_minScore', $minScore);

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    private function addFilters(string $definition, Criteria $criteria, QueryBuilder $query, ShopContext $context): void
    {
        $parsed = SqlQueryParser::parse($criteria->getAllFilters(), $definition, $context);

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    private function addSortings(string $definition, Criteria $criteria, QueryBuilder $query, ShopContext $context): void
    {
        /* @var string|EntityDefinition $definition */
        foreach ($criteria->getSortings() as $sorting) {
            $query->addOrderBy(
                EntityDefinitionQueryHelper::getFieldAccessor(
                    $sorting->getField(),
                    $definition,
                    $definition::getEntityName(),
                    $context
                ),
                $sorting->getDirection()
            );
        }
    }

    private function addFetchCount(Criteria $criteria, QueryBuilder $query): void
    {
        //requires total count for query? add save SQL_CALC_FOUND_ROWS
        if ($criteria->fetchCount() === Criteria::FETCH_COUNT_NONE) {
            return;
        }
        if ($criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $query->setMaxResults($criteria->getLimit() * 6 + 1);

            return;
        }

        $selects = $query->getQueryPart('select');
        $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
        $query->select($selects);
    }

    private function addGroupBy(string $definition, Criteria $criteria, QueryBuilder $query, ShopContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        if (!$query->hasState(EntityDefinitionQueryHelper::REQUIRES_GROUP_BY)) {
            return;
        }

        $fields = [
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id'),
        ];

        // each order by column has to be inside the group by statement (sql_mode=only_full_group_by)
        foreach ($criteria->getSortings() as $sorting) {
            $fields[] = EntityDefinitionQueryHelper::getFieldAccessor(
                $sorting->getField(),
                $definition,
                $definition::getEntityName(),
                $context
            );
        }

        $fields = array_unique($fields);

        foreach ($fields as $field) {
            $query->addGroupBy($field);
        }
    }
}
