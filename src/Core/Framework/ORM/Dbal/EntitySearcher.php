<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Search\Parser\SqlQueryParser;
use Shopware\Framework\ORM\Search\Query\ScoreQuery;
use Shopware\Framework\Struct\Uuid;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 */
class EntitySearcher implements EntitySearcherInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SqlQueryParser
     */
    private $queryParser;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(
        Connection $connection,
        SqlQueryParser $queryParser,
        EntityDefinitionQueryHelper $queryHelper
    ) {
        $this->connection = $connection;
        $this->queryParser = $queryParser;
        $this->queryHelper = $queryHelper;
    }

    public function search(string $definition, Criteria $criteria, ApplicationContext $context): IdSearchResult
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = $this->queryHelper->getBaseQuery($this->connection, $definition, $context);

        if ($definition::getParentPropertyName()) {
            /** @var EntityDefinition|string $definition */
            $parent = $definition::getFields()->get($definition::getParentPropertyName());
            $this->queryHelper->resolveField($parent, $definition, $definition::getEntityName(), $query, $context);
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
            if ($fieldName === '_score') {
                continue;
            }
            $this->queryHelper->resolveAccessor($fieldName, $definition, $table, $query, $context);
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

        $total = $this->getTotalCount($criteria, $data);

        if ($criteria->fetchCount() === Criteria::FETCH_COUNT_NEXT_PAGES) {
            $data = array_slice($data, 0, $criteria->getLimit());
        }

        $converted = [];
        foreach ($data as $key => $values) {
            $key = Uuid::fromBytesToHex($key);
            $values['primary_key'] = $key;
            $converted[$key] = $values;
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    private function addQueries(string $definition, Criteria $criteria, QueryBuilder $query, ApplicationContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $queries = $this->queryParser->parseRanking(
            $criteria->getQueries(),
            $definition,
            $definition::getEntityName(),
            $context
        );
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ')';
        $query->addSelect($select . ' as _score');

        if (empty($criteria->getSortings())) {
            $query->addOrderBy('_score', 'DESC');
        }

        $minScore = array_map(function (ScoreQuery $query) {
            return $query->getScore();
        }, $criteria->getQueries());

        $minScore = min($minScore);

        $query->andHaving('_score >= :_minScore');
        $query->setParameter('_minScore', $minScore);

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    private function addFilters(string $definition, Criteria $criteria, QueryBuilder $query, ApplicationContext $context): void
    {
        $parsed = $this->queryParser->parse($criteria->getAllFilters(), $definition, $context);

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    private function addSortings(string $definition, Criteria $criteria, QueryBuilder $query, ApplicationContext $context): void
    {
        /* @var string|EntityDefinition $definition */
        foreach ($criteria->getSortings() as $sorting) {
            if ($sorting->getField() === '_score') {
                $query->addOrderBy('_score', $sorting->getDirection());
                continue;
            }

            $query->addOrderBy(
                $this->queryHelper->getFieldAccessor(
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

    private function addGroupBy(string $definition, Criteria $criteria, QueryBuilder $query, ApplicationContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        if (!$query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN)) {
            return;
        }

        $fields = [
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id'),
        ];

        // each order by column has to be inside the group by statement (sql_mode=only_full_group_by)
        foreach ($criteria->getSortings() as $sorting) {
            if ($sorting->getField() === '_score') {
                continue;
            }

            $fields[] = $this->queryHelper->getFieldAccessor(
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

    private function getTotalCount(Criteria $criteria, array $data): int
    {
        if ($criteria->fetchCount() === Criteria::FETCH_COUNT_TOTAL) {
            return (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        }

        return \count($data);
    }
}
