<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 */
class EntitySearcher implements EntitySearcherInterface
{
    use CriteriaQueryHelper;

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

    /**
     * @var SearchTermInterpreter
     */
    private $interpreter;

    /**
     * @var EntityScoreQueryBuilder
     */
    private $scoreBuilder;

    public function __construct(
        Connection $connection,
        SqlQueryParser $queryParser,
        EntityDefinitionQueryHelper $queryHelper,
        SearchTermInterpreter $interpreter,
        EntityScoreQueryBuilder $scoreBuilder
    ) {
        $this->connection = $connection;
        $this->queryParser = $queryParser;
        $this->queryHelper = $queryHelper;
        $this->interpreter = $interpreter;
        $this->scoreBuilder = $scoreBuilder;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        $table = $definition->getEntityName();

        $query = new QueryBuilder($this->connection);
        $query->select([
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as array_key',
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as id',
        ]);

        if (!empty($criteria->getIds())) {
            $criteria->addFilter(new EqualsAnyFilter($table . '.id', $criteria->getIds()));
        }

        $query = $this->buildQueryByCriteria($query, $definition, $criteria, $context);

        if ($query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN)) {
            $query->addGroupBy(
                EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id')
            );
        }

        //add pagination
        if ($criteria->getOffset() !== null) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() !== null) {
            $query->setMaxResults($criteria->getLimit());
        }

        $this->addTotalCountMode($criteria, $query);

        //execute and fetch ids
        $data = $query->execute()->fetchAll();
        $data = FetchModeHelper::groupUnique($data);

        $total = $this->getTotalCount($table, $query, $criteria, $data);

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $data = \array_slice($data, 0, $criteria->getLimit());
        }

        $converted = [];
        foreach ($data as $key => $values) {
            $key = Uuid::fromBytesToHex($key);
            $values['id'] = $key;
            $converted[$key] = $values;
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    protected function getParser(): SqlQueryParser
    {
        return $this->queryParser;
    }

    protected function getDefinitionHelper(): EntityDefinitionQueryHelper
    {
        return $this->queryHelper;
    }

    protected function getInterpreter(): SearchTermInterpreter
    {
        return $this->interpreter;
    }

    protected function getScoreBuilder(): EntityScoreQueryBuilder
    {
        return $this->scoreBuilder;
    }

    private function addTotalCountMode(Criteria $criteria, QueryBuilder $query): void
    {
        //requires total count for query? add save SQL_CALC_FOUND_ROWS
        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NONE) {
            return;
        }
        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $query->setMaxResults($criteria->getLimit() * 6 + 1);

            return;
        }

        if ($query->hasState('_score')) {
            $selects = $query->getQueryPart('select');
            $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
            $query->select($selects);
        }
    }

    private function getTotalCount(string $table, QueryBuilder $query, Criteria $criteria, array $data): int
    {
        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_EXACT) {
            return \count($data);
        }

        if ($query->hasState('_score')) {
            return (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        }

        $id = EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id');

        $selects = $query->getQueryPart('select');
        $selects[0] = 'DISTINCT ' . $selects[0];

        $query->select([
            'COUNT(DISTINCT ' . $id . ') as total',
        ]);

        $query->setMaxResults(1);
        $query->setFirstResult(0);
        $query->resetQueryPart('groupBy');
        $query->resetQueryPart('orderBy');

        return (int) $query->execute()->fetchColumn();
    }
}
