<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Facade;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelRepositoryFacade
{
    private SalesChannelDefinitionInstanceRegistry $registry;

    private RequestCriteriaBuilder $criteriaBuilder;

    private SalesChannelContext $context;

    public function __construct(
        SalesChannelDefinitionInstanceRegistry $registry,
        RequestCriteriaBuilder $criteriaBuilder,
        SalesChannelContext $context
    ) {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->context = $context;
    }

    public function search(string $entityName, array $criteria): EntitySearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getSalesChannelRepository($entityName);

        return $repository->search($criteriaObject, $this->context);
    }

    public function ids(string $entityName, array $criteria): IdSearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getSalesChannelRepository($entityName);

        return $repository->searchIds($criteriaObject, $this->context);
    }

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
