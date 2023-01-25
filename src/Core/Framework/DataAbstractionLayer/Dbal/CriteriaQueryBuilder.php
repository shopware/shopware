<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
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
#[Package('core')]
class CriteriaQueryBuilder
{
    public function __construct(
        private readonly SqlQueryParser $parser,
        /***
         * @var EntityDefinitionQueryHelper
         */
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly SearchTermInterpreter $interpreter,
        private readonly EntityScoreQueryBuilder $scoreBuilder,
        private readonly JoinGroupBuilder $joinGrouper,
        private readonly CriteriaPartResolver $criteriaPartResolver
    ) {
    }

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
            $pattern = $this->interpreter->interpret((string) $criteria->getTerm());
            $queries = $this->scoreBuilder->buildScoreQueries($pattern, $definition, $definition->getEntityName(), $context);
            $criteria->addQuery(...$queries);
        }

        $filters = $this->groupFilters($definition, $criteria, $paths);

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

        if (empty($parsed->getWheres())) {
            return;
        }

        $query->andWhere(implode(' AND ', $parsed->getWheres()));
        foreach ($parsed->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $parsed->getType($key));
        }
    }

    public function addSortings(EntityDefinition $definition, Criteria $criteria, array $sortings, QueryBuilder $query, Context $context): void
    {
        /** @var FieldSorting $sorting */
        foreach ($sortings as $sorting) {
            $this->validateSortingDirection($sorting->getDirection());

            if ($sorting->getField() === '_score') {
                if (!$this->hasQueriesOrTerm($criteria)) {
                    continue;
                }

                // Only add manual _score sorting if the query contains a _score calculation and selection (i.e. the
                // criteria has a term or queries). Otherwise the SQL selection would fail because no _score field
                // exists in any entity.
                $query->addOrderBy('_score', $sorting->getDirection());
                $query->addState('_score');

                continue;
            }

            $accessor = $this->helper->getFieldAccessor($sorting->getField(), $definition, $definition->getEntityName(), $context);

            if ($sorting instanceof CountSorting) {
                $query->addOrderBy(sprintf('COUNT(%s)', $accessor), $sorting->getDirection());

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
        if (empty($queries->getWheres())) {
            return;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $primary = $definition->getPrimaryKeys()->first();

        \assert($primary instanceof StorageAware);

        $select = 'SUM(' . implode(' + ', $queries->getWheres()) . ') / ' . \sprintf('COUNT(%s.%s)', $definition->getEntityName(), $primary->getStorageName());
        $query->addSelect($select . ' as _score');

        // Sort by _score primarily if the criteria has a score query or search term
        if (!$this->hasScoreSorting($criteria)) {
            $criteria->addSorting(new FieldSorting('_score', FieldSorting::DESCENDING));
        }

        $minScore = array_map(fn (ScoreQuery $query) => $query->getScore(), $criteria->getQueries());

        $minScore = min($minScore);

        $query->andHaving('_score >= :_minScore');
        $query->setParameter('_minScore', $minScore);
        $query->addState('_score');

        foreach ($queries->getParameters() as $key => $value) {
            $query->setParameter($key, $value, $queries->getType($key));
        }
    }

    private function hasGroupBy(Criteria $criteria, QueryBuilder $query): bool
    {
        if ($query->hasState(EntityReader::MANY_TO_MANY_LIMIT_QUERY)) {
            return false;
        }

        return $query->hasState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN) || !empty($criteria->getGroupFields());
    }

    private function groupFilters(EntityDefinition $definition, Criteria $criteria, array $additionalFields = []): array
    {
        $filters = [];
        foreach ($criteria->getFilters() as $filter) {
            $filters[] = new AndFilter([$filter]);
        }

        foreach ($criteria->getPostFilters() as $filter) {
            $filters[] = new AndFilter([$filter]);
        }

        // $additionalFields is used by the entity aggregator.
        // For example, if an aggregation is to be created on a to many association that is already stored as a filter.
        // The association is therefore referenced twice in the query and would have to be created as a sub-join in each case. But since only the filters are considered, the association is referenced only once.
        return $this->joinGrouper->group($filters, $definition, $additionalFields);
    }

    private function hasScoreSorting(Criteria $criteria): bool
    {
        foreach ($criteria->getSorting() as $sorting) {
            if ($sorting->getField() === '_score') {
                return true;
            }
        }

        return false;
    }

    private function hasQueriesOrTerm(Criteria $criteria): bool
    {
        return !empty($criteria->getQueries()) || $criteria->getTerm();
    }

    /**
     * @throws InvalidSortingDirectionException
     */
    private function validateSortingDirection(string $direction): void
    {
        if (!\in_array(mb_strtoupper($direction), [FieldSorting::ASCENDING, FieldSorting::DESCENDING], true)) {
            throw new InvalidSortingDirectionException($direction);
        }
    }
}
