<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Parser\SqlQueryParser;
use Shopware\Api\Entity\Search\UuidSearchResult;
use Shopware\Api\Search\Query\ScoreQuery;
use Shopware\Context\Struct\TranslationContext;

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

    public function search(string $definition, Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();
        $query = new QueryBuilder($this->connection);

        //add uuid select, e.g. `product`.`uuid`;
        $query->addSelect(
            EntityDefinitionResolver::escape($table) . '.' . EntityDefinitionResolver::escape('uuid') . ' as array_key',
            EntityDefinitionResolver::escape($table) . '.' . EntityDefinitionResolver::escape('uuid') . ' as primary_key'
        );

        //build from path with escaped alias, e.g. FROM product as `product`
        $query->from(
            EntityDefinitionResolver::escape($table),
            EntityDefinitionResolver::escape($table)
        );

        $fields = array_merge(
            $criteria->getSortingFields(),
            $criteria->getFilterFields(),
            $criteria->getPostFilterFields(),
            $criteria->getQueryFields()
        );

        //join association and translated fields
        foreach ($fields as $fieldName) {
            EntityDefinitionResolver::joinField($fieldName, $definition, $table, $query, $context);
        }

        $this->addFilters($definition, $criteria, $query);

        $this->addQueries($definition, $criteria, $query);

        $this->addSortings($definition, $criteria, $query);

        $this->addFetchCount($criteria, $query);

        $this->addGroupBy($definition, $criteria, $query);

        //add pagination
        if ($criteria->getOffset() >= 0) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() >= 0) {
            $query->setMaxResults($criteria->getLimit());
        }

        //execute and fetch uuids
        $data = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        if ($criteria->fetchCount()) {
            $total = (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        } else {
            $total = count($data);
        }

        return new UuidSearchResult($total, $data, $criteria, $context);
    }

    private function addQueries(string $definition, Criteria $criteria, QueryBuilder $query): void
    {
        /** @var string|EntityDefinition $definition */
        $queries = SqlQueryParser::parseRanking($criteria->getQueries(), $definition, $definition::getEntityName());
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionResolver::REQUIRES_GROUP_BY);

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

    private function addFilters(string $definition, Criteria $criteria, QueryBuilder $query): void
    {
        $parsed = SqlQueryParser::parse($criteria->getAllFilters(), $definition);

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    private function addSortings(string $definition, Criteria $criteria, QueryBuilder $query): void
    {
        /* @var string|EntityDefinition $definition */
        foreach ($criteria->getSortings() as $sorting) {
            $query->addOrderBy(
                EntityDefinitionResolver::resolveField(
                    $sorting->getField(),
                    $definition,
                    $definition::getEntityName()
                ),
                $sorting->getDirection()
            );
        }
    }

    private function addFetchCount(Criteria $criteria, QueryBuilder $query): void
    {
        //requires total count for query? add save SQL_CALC_FOUND_ROWS
        if (!$criteria->fetchCount()) {
            return;
        }

        $selects = $query->getQueryPart('select');
        $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
        $query->select($selects);
    }

    private function addGroupBy(string $definition, Criteria $criteria, QueryBuilder $query): void
    {
        /** @var string|EntityDefinition $definition */
        $table = $definition::getEntityName();

        if (!$query->hasState(EntityDefinitionResolver::REQUIRES_GROUP_BY)) {
            return;
        }

        $fields = [
            EntityDefinitionResolver::escape($table) . '.' . EntityDefinitionResolver::escape('uuid'),
        ];

        // each order by column has to be inside the group by statement (sql_mode=only_full_group_by)
        foreach ($criteria->getSortings() as $sorting) {
            $fields[] = EntityDefinitionResolver::resolveField(
                $sorting->getField(),
                $definition,
                $definition::getEntityName()
            );
        }

        $fields = array_unique($fields);

        foreach ($fields as $field) {
            $query->addGroupBy($field);
        }
    }
}
