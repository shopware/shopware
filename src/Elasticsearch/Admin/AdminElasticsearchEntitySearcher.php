<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Exception\EmptyQueryException;

#[Package('framework')]
class AdminElasticsearchEntitySearcher implements EntitySearcherInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntitySearcherInterface $decorated,
        private readonly AdminSearchRegistry $registry,
        private readonly AdminElasticsearchHelper $helper,
        private readonly AdminSearcher $searcher,
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->allowAdminEsSearch($definition, $context, $criteria)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        try {
            return $this->searcher->searchIds(
                $definition->getEntityName(),
                $criteria,
                $context
            );
        } catch (\Throwable $e) {
            if ($e instanceof EmptyQueryException) {
                return new IdSearchResult(0, [], $criteria, $context);
            }

            $this->helper->logAndThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }
    }

    private function allowAdminEsSearch(EntityDefinition $definition, Context $context, Criteria $criteria): bool
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return false;
        }

        if (!$context->getSource() instanceof AdminApiSource) {
            return false;
        }

        if (!empty($criteria->getIds())) {
            return false;
        }

        if (!$this->helper->isEnabled()) {
            return false;
        }

        if (!$this->registry->hasIndexer($definition->getEntityName())) {
            return false;
        }

        $indexer = $this->registry->getIndexer($definition->getEntityName());

        // no field is marked for ES index, skip it
        if ($indexer->mapping([]) === []) {
            return false;
        }

        // if no filters, aggregations, queries etc, we can use es
        if ($criteria->getTerm() && $criteria->getAllFields() === []) {
            return true;
        }

        // if criteria contains unsupported fields, we cannot use es
        if (\count(array_diff(
            $criteria->getAllFields(),
            $indexer->getSupportedSearchFields()
        )) > 0) {
            return false;
        }

        if ($this->criteriaHasUnsupportedFeatures($criteria)) {
            return false;
        }

        return true;
    }

    /**
     * @description Checks if the criteria contains filters or queries that are not supported by the our implementation. Goal is to prevent sending queries to ES that we know will fail and fallback to the default search implementation instead.
     * We currently do not support ContainsFilter, PrefixFilter and SuffixFilter in the admin ES search, as they would require a full reimplementation of the way we parse filters for the admin search but all index fields are keyword fields where these filters would not work anyway.
     * This is because we want to minimize offloading to ES as much as possible and only want to use it for very simple queries with term and filters on keyword fields.
     * For "searching" functionality in the admin we recommend using the criteria.setTerm() functionality which is supported by text field that are part of every admin ES indexes in current implementation.
     */
    private function criteriaHasUnsupportedFeatures(Criteria $criteria): bool
    {
        $filters = [...$criteria->getFilters(), ...$criteria->getPostFilters()];

        foreach ($criteria->getQueries() as $scoreQuery) {
            $filters[] = $scoreQuery->getQuery();
        }

        foreach ($filters as $filter) {
            if ($this->unsupportedFilter($filter)) {
                return true;
            }
        }

        return false;
    }

    private function unsupportedFilter(Filter $filter): bool
    {
        if ($filter instanceof ContainsFilter || $filter instanceof PrefixFilter || $filter instanceof SuffixFilter) {
            return true;
        }

        if ($filter instanceof MultiFilter) {
            foreach ($filter->getQueries() as $childFilter) {
                if ($this->unsupportedFilter($childFilter)) {
                    return true;
                }
            }
        }

        return false;
    }
}
