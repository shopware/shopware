<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * The `repository` service allows you to query data, that is stored inside shopware.
 * Keep in mind that your app needs to have the correct permissions for the data it queries through this service.
 *
 * @script-service data_loading
 */
#[Package('core')]
class RepositoryFacade
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly AclCriteriaValidator $criteriaValidator,
        private readonly Context $context
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
     * @example repository-search-by-id/script.twig Load a single product.
     * @example repository-filter/script.twig Filter the search result.
     * @example repository-association/script.twig Add associations that should be included in the result.
     */
    public function search(string $entityName, array $criteria): EntitySearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

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
     * @example repository-search-ids/script.twig Get the Ids of products with the given ProductNumber.
     */
    public function ids(string $entityName, array $criteria): IdSearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

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
     * @example repository-aggregate/script.twig Aggregate data for multiple entities, e.g. the sum of the gross price of all products.
     */
    public function aggregate(string $entityName, array $criteria): AggregationResultCollection
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->aggregate($criteriaObject, $this->context);
    }

    private function prepareCriteria(string $entityName, array $criteria): Criteria
    {
        $definition = $this->registry->getByEntityName($entityName);
        $criteriaObject = new Criteria();

        $this->criteriaBuilder->fromArray($criteria, $criteriaObject, $definition, $this->context);

        $missingPermissions = $this->criteriaValidator->validate($entityName, $criteriaObject, $this->context);

        if (!empty($missingPermissions)) {
            throw new MissingPrivilegeException($missingPermissions);
        }

        return $criteriaObject;
    }
}
