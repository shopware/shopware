<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The `store` service can be used to access publicly available `store-api` data.
 * As the data is publicly available your app does not need any additional permissions to use this service,
 * however querying data and also loading associations is restricted to the entities that are also available through the `store-api`.
 *
 * Notice that the returned entities are already processed for the storefront,
 * this means that e.g. product prices are already calculated based on the current context.
 *
 * @script-service data_loading
 */
#[Package('core')]
class SalesChannelRepositoryFacade
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelDefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly SalesChannelContext $context
    ) {
    }

    /**
     * The `search()` method allows you to search for Entities that match a given criteria.
     *
     * @param string $entityName The name of the Entity you want to search for, e.g. `product` or `media`.
     * @param array $criteria The criteria used for your search.
     *
     * @return EntitySearchResult A `EntitySearchResult` including all entities that matched your criteria.
     *
     * @example store-search-by-id/script.twig Load a single storefront product.
     * @example store-filter/script.twig Filter the search result.
     * @example store-association/script.twig Add associations that should be included in the result.
     */
    public function search(string $entityName, array $criteria): EntitySearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getSalesChannelRepository($entityName);

        return $repository->search($criteriaObject, $this->context);
    }

    /**
     * The `ids()` method allows you to search for the Ids of Entities that match a given criteria.
     *
     * @param string $entityName The name of the Entity you want to search for, e.g. `product` or `media`.
     * @param array $criteria The criteria used for your search.
     *
     * @return IdSearchResult A `IdSearchResult` including all entity-ids that matched your criteria.
     *
     * @example store-search-ids/script.twig Get the Ids of products with the given ProductNumber.
     */
    public function ids(string $entityName, array $criteria): IdSearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getSalesChannelRepository($entityName);

        return $repository->searchIds($criteriaObject, $this->context);
    }

    /**
     * The `aggregate()` method allows you to execute aggregations specified in the given criteria.
     *
     * @param string $entityName The name of the Entity you want to aggregate data on, e.g. `product` or `media`.
     * @param array $criteria The criteria that define your aggregations.
     *
     * @return AggregationResultCollection A `AggregationResultCollection` including the results of the aggregations you specified in the criteria.
     *
     * @example store-aggregate/script.twig Aggregate data for multiple entities, e.g. the sum of the children of all products.
     */
    public function aggregate(string $entityName, array $criteria): AggregationResultCollection
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getSalesChannelRepository($entityName);

        return $repository->aggregate($criteriaObject, $this->context);
    }

    private function prepareCriteria(string $entityName, array $criteria): Criteria
    {
        $definition = $this->registry->getByEntityName($entityName);
        $criteriaObject = new Criteria();

        $this->criteriaBuilder->fromArray($criteria, $criteriaObject, $definition, $this->context->getContext());

        return $criteriaObject;
    }
}
