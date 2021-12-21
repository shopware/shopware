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

class RepositoryFacade
{
    private DefinitionInstanceRegistry $registry;

    private RequestCriteriaBuilder $criteriaBuilder;

    private AclCriteriaValidator $criteriaValidator;

    private Context $context;

    public function __construct(
        DefinitionInstanceRegistry $registry,
        RequestCriteriaBuilder $criteriaBuilder,
        AclCriteriaValidator $criteriaValidator,
        Context $context
    ) {
        $this->registry = $registry;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->criteriaValidator = $criteriaValidator;
        $this->context = $context;
    }

    public function search(string $entityName, array $criteria): EntitySearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->search($criteriaObject, $this->context);
    }

    public function ids(string $entityName, array $criteria): IdSearchResult
    {
        $criteriaObject = $this->prepareCriteria($entityName, $criteria);

        $repository = $this->registry->getRepository($entityName);

        return $repository->searchIds($criteriaObject, $this->context);
    }

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
