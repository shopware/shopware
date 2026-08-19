<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class CriteriaQueryBuilder
{
    /**
     * MySQL and MariaDB can only reference 61 tables in a single join. Above this
     * many joins the criteria is built with its filter-only associations resolved
     * as `EXISTS` sub queries, which are query blocks of their own and therefore
     * do not consume one of the 61 slots. The estimate in {@see estimateJoins} is
     * deliberately rough, so this leaves headroom for the tables the base query,
     * its translations and the inheritance parent add on top.
     */
    private const JOIN_BUDGET = 50;

    public function __construct(
        private readonly SqlQueryParser $parser,
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly SearchTermInterpreter $interpreter,
        private readonly EntityScoreQueryBuilder $scoreBuilder,
        private readonly JoinGroupBuilder $joinGrouper,
        private readonly CriteriaPartResolver $criteriaPartResolver
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public function build(QueryBuilder $query, EntityDefinition $definition, Criteria $criteria, Context $context, array $paths = []): QueryBuilder
    {
        $query = $this->helper->getBaseQuery($query, $definition, $context);

        if ($definition->isInheritanceAware() && $context->considerInheritance()) {
            $parent = $definition->getFields()->get('parent');

            if ($parent) {
                $this->helper->resolveField($parent, $definition, $definition->getEntityName(), $query, $context);
            }
        }

        if ($criteria->getTerm()) {
            $pattern = $this->interpreter->interpret($criteria->getTerm());
            $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition->getEntityName(), $context);
            $criteria->addQuery(...$queries);
        }

        $filters = $this->groupFilters($definition, $criteria, $context, $paths);

        $this->criteriaPartResolver->resolve($filters, $definition, $query, $context);

        $this->criteriaPartResolver->resolve($criteria->getQueries(), $definition, $query, $context);

        $this->criteriaPartResolver->resolve($criteria->getSorting(), $definition, $query, $context);

        // do not use grouped filters, because the grouped filters are mapped flat and the logical OR/AND are removed
        $filter = new AndFilter(array_merge(
            $criteria->getFilters(),
            $criteria->getPostFilters()
        ));

        $this->addFilter($definition, $filter, $query, $context);

        $this->addQueries($definition, $criteria, $query, $context);

        if ($criteria->getLimit() === 1) {
            $query->removeState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);
        }

        $this->addSortings($definition, $criteria, $criteria->getSorting(), $query, $context);

        return $query;
    }

    public function addFilter(EntityDefinition $definition, ?Filter $filter, QueryBuilder $query, Context $context): void
    {
        if (!$filter) {
            return;
        }

        $parsed = $this->parser->parse($filter, $definition, $context);

        if ($parsed->getWheres() === []) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    /**
     * @param array<FieldSorting> $sortings
     */
    public function addSortings(EntityDefinition $definition, Criteria $criteria, array $sortings, QueryBuilder $query, Context $context): void
    {
        foreach ($sortings as $sorting) {
            $this->validateSortingDirection($sorting->getDirection());

            if ($sorting->getField() === Criteria::SCORE_FIELD) {
                if (!$this->hasQueriesOrTerm($criteria)) {
                    continue;
                }

                // Only add manual _score sorting if the query contains a _score calculation and selection (i.e. the
                // criteria has a term or queries). Otherwise the SQL selection would fail because no _score field
                // exists in any entity.
                $query->addOrderBy(Criteria::SCORE_FIELD, $sorting->getDirection());
                $query->addState(Criteria::SCORE_FIELD);

                continue;
            }

            $accessor = $this->helper->getFieldAccessor($sorting->getField(), $definition, $definition->getEntityName(), $context);

            if ($sorting instanceof CountSorting) {
                $query->addOrderBy(\sprintf('COUNT(%s)', $accessor), $sorting->getDirection());

                continue;
            }

            if ($sorting->getNaturalSorting()) {
                $query->addOrderBy('LENGTH(' . $accessor . ')', $sorting->getDirection());
            }

            if (!$this->hasGroupBy($criteria, $query)) {
                $query->addOrderBy($accessor, $sorting->getDirection());

                continue;
            }

            if (!\in_array($sorting->getField(), ['product.cheapestPrice', 'cheapestPrice'], true)) {
                if ($sorting->getDirection() === FieldSorting::ASCENDING) {
                    $accessor = 'MIN(' . $accessor . ')';
                } else {
                    $accessor = 'MAX(' . $accessor . ')';
                }
            } else {
                $accessor = 'MIN(' . $accessor . ')';
            }
            $query->addOrderBy($accessor, $sorting->getDirection());
        }
    }

    private function addQueries(EntityDefinition $definition, Criteria $criteria, QueryBuilder $query, Context $context): void
    {
        $queries = $this->parser->parseRanking(
            $criteria->getQueries(),
            $definition,
            $definition->getEntityName(),
            $context
        );

        if ($queries->getWheres() === []) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $primary = $definition->getPrimaryKeys()->first();

        \assert($primary instanceof StorageAware);

        $distincts = [];

        foreach ($criteria->getQueries() as $scoreQuery) {
            if (!$scoreQuery->getScoreField() || \array_key_exists($scoreQuery->getScoreField(), $distincts)) {
                continue;
            }

            $associatedDefinition = EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $scoreQuery->getScoreField());

            if ($associatedDefinition === $definition) {
                continue;
            }

            $associationPath = EntityDefinitionQueryHelper::getAssociationPath($scoreQuery->getScoreField(), $definition);
            $associationPrimary = $associatedDefinition->getPrimaryKeys()->first();

            \assert($associationPrimary instanceof StorageAware);

            $field = $this->helper->getFieldAccessor(
                \sprintf('%s.%s', (string) $associationPath, $associationPrimary->getPropertyName()),
                $definition,
                $definition->getEntityName(),
                $context
            );

            $distincts[$scoreQuery->getScoreField()] = \sprintf('COUNT(DISTINCT %s)', $field);
        }

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ') / ' . \sprintf('COUNT(%s.%s)', $definition->getEntityName(), $primary->getStorageName());

        if ($distincts !== []) {
            $select .= ' * (' . implode(' + ', $distincts) . ')';
        }

        $query->addSelect($select . ' as _score');
        $this->addConditions($criteria->getQueries(), $definition, $query, $context);

        // Sort by _score primarily if the criteria has a score query or search term
        if (!$this->hasScoreSorting($criteria)) {
            $criteria->addSorting(new FieldSorting(Criteria::SCORE_FIELD, FieldSorting::DESCENDING));
        }

        $minScore = array_map(static fn (ScoreQuery $query) => $query->getScore(), $criteria->getQueries());
        \assert($minScore !== []);

        $minScore = min($minScore);

        $query->andHaving('_score >= :_minScore');
        $query->setParameter('_minScore', $minScore);
        $query->addState(Criteria::SCORE_FIELD);

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    /**
     * @param array<ScoreQuery> $queries
     */
    private function addConditions(array $queries, EntityDefinition $definition, QueryBuilder $query, Context $context): void
    {
        $conditions = [];
        foreach ($queries as $scoreQuery) {
            $parsed = $this->parser->parse($scoreQuery->getQuery(), $definition, $context);

            if ($parsed->getWheres() === []) {
                continue;
            }

            $conditions = array_merge($conditions, $parsed->getWheres());

            foreach ($parsed->getParameters() as $key => $value) {
                $query->setParameter($key, $value, $parsed->getType($key));
            }
        }

        if ($conditions === []) {
            return;
        }

        $wheres = implode(' OR ', $conditions);
        $query->andWhere($wheres);
    }

    private function hasGroupBy(Criteria $criteria, QueryBuilder $query): bool
    {
        if ($query->hasState(EntityReader::TO_MANY_ASSOCIATION_LIMIT_QUERY)) {
            return false;
        }

        return $query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN) || $criteria->getGroupFields() !== [];
    }

    /**
     * @param list<string> $additionalFields
     *
     * @return list<Filter>
     */
    private function groupFilters(EntityDefinition $definition, Criteria $criteria, Context $context, array $additionalFields = []): array
    {
        $filters = [];
        foreach ($criteria->getFilters() as $filter) {
            $filters[] = new AndFilter([$filter]);
        }

        foreach ($criteria->getPostFilters() as $filter) {
            $filters[] = new AndFilter([$filter]);
        }

        // $additionalFields is used by the entity aggregator.
        // For example, if an aggregation is to be created on a to-many-association that is already stored as a filter.
        // The association is therefore referenced twice in the query and would have to be created as a sub-join in each case. But since only the filters are considered, the association is referenced only once.
        return $this->joinGrouper->group(
            $filters,
            $definition,
            $additionalFields,
            $this->estimateJoins($definition, $criteria, $context) > self::JOIN_BUDGET,
            $this->getKeepJoinedPaths($definition, $criteria)
        );
    }

    /**
     * Associations that sortings, groupings or score queries read from have to be
     * joined for real: those clauses reference the joined alias, which an `EXISTS`
     * sub query does not provide.
     *
     * @return list<string>
     */
    private function getKeepJoinedPaths(EntityDefinition $definition, Criteria $criteria): array
    {
        $accessors = [];
        foreach ($criteria->getSorting() as $sorting) {
            $accessors[] = $sorting->getField();
        }

        foreach ($criteria->getGroupFields() as $grouping) {
            $accessors[] = $grouping->getField();
        }

        foreach ($criteria->getQueries() as $query) {
            foreach ($query->getFields() as $field) {
                $accessors[] = $field;
            }
        }

        $paths = [];
        foreach ($accessors as $accessor) {
            if ($accessor === Criteria::SCORE_FIELD) {
                continue;
            }

            $path = JoinGroupBuilder::findToManyPath($definition, $accessor);

            if ($path !== null) {
                $paths[$path] = true;
            }
        }

        return array_keys($paths);
    }

    /**
     * Rough upper estimate of how many tables the criteria will join. It is only
     * used to decide whether the query needs to spill into sub queries, so it does
     * not have to be exact: too low simply keeps today's behaviour.
     */
    private function estimateJoins(EntityDefinition $definition, Criteria $criteria, Context $context): int
    {
        $accessors = [];
        foreach ([...$criteria->getFilters(), ...$criteria->getPostFilters()] as $filter) {
            foreach ($filter->getFields() as $field) {
                $accessors[] = $field;
            }
        }

        foreach ($criteria->getSorting() as $sorting) {
            $accessors[] = $sorting->getField();
        }

        foreach ($criteria->getGroupFields() as $grouping) {
            $accessors[] = $grouping->getField();
        }

        $joins = [];
        foreach ($accessors as $accessor) {
            foreach ($this->joinsOfAccessor($definition, $accessor, $context) as $join) {
                $joins[$join] = true;
            }
        }

        return \count($joins);
    }

    /**
     * @return list<string> one key per table the accessor makes the query join
     */
    private function joinsOfAccessor(EntityDefinition $definition, string $accessor, Context $context): array
    {
        $parts = explode('.', str_replace('extensions.', '', $accessor));
        if ($parts[0] === $definition->getEntityName()) {
            array_shift($parts);
        }

        $joins = [];
        $path = [$definition->getEntityName()];

        foreach ($parts as $part) {
            $field = $definition->getFields()->get($part);

            if ($field instanceof TranslatedField) {
                $joins[] = implode('.', $path) . '.translation';

                break;
            }

            if (!$field instanceof AssociationField) {
                break;
            }

            $path[] = $field->getPropertyName();
            $alias = implode('.', $path);
            $joins[] = $alias;

            if ($field instanceof ManyToManyAssociationField) {
                $joins[] = $alias . '.mapping';
                $definition = $field->getToManyReferenceDefinition();
            } else {
                $definition = $field->getReferenceDefinition();
            }

            if ($context->considerInheritance() && $definition->isInheritanceAware()) {
                $joins[] = $alias . '.parent';
            }
        }

        return $joins;
    }

    private function hasScoreSorting(Criteria $criteria): bool
    {
        foreach ($criteria->getSorting() as $sorting) {
            if ($sorting->getField() === Criteria::SCORE_FIELD) {
                return true;
            }
        }

        return false;
    }

    private function hasQueriesOrTerm(Criteria $criteria): bool
    {
        return $criteria->getQueries() !== [] || $criteria->getTerm();
    }

    private function validateSortingDirection(string $direction): void
    {
        if (!\in_array(mb_strtoupper($direction), [FieldSorting::ASCENDING, FieldSorting::DESCENDING], true)) {
            throw DataAbstractionLayerException::invalidSortingDirection($direction);
        }
    }
}
