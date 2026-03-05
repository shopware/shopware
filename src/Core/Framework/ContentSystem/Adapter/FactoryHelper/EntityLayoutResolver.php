<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves content layout assignments and processes placeholders for entity-based rendering.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityLayoutResolver
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function resolve(
        string $entityId,
        Request $request,
        SalesChannelContext $context,
        EntityRepository $repository,
        AbstractContentLayoutAssignableDefinition $definition
    ): LayoutResolutionResult {
        $entityIdField = $definition->getContentLayoutEntityIdField();
        $entityType = $definition->getContentLayoutEntityType();

        $assignment = $this->findLayoutAssignment($entityIdField, $entityId, $context, $repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                $entityType,
                $entityId,
                $context->getSalesChannel()->getId()
            );
        }

        $placeholderValues = $this->buildPlaceholderValues($entityIdField, $entityId, $request);

        return new LayoutResolutionResult(
            assignment: $assignment,
            placeholderValues: $placeholderValues
        );
    }

    /**
     * Finds layout assignment with sales channel fallback priority: specific → global (null).
     *
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function findLayoutAssignment(
        string $entityIdField,
        string $entityId,
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?AbstractContentLayoutAssignmentEntity {
        $criteria = $this->buildAssignmentCriteria($entityIdField, $entityId, $context);
        $criteria->addAssociation('contentLayout');

        $entity = $repository->search($criteria, $context->getContext())->first();

        if (!$entity instanceof AbstractContentLayoutAssignmentEntity) {
            return null;
        }

        return $entity;
    }

    /**
     * Returns only the content layout ID without loading the full assignment or contentLayout association.
     *
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    public function findLayoutId(
        string $entityIdField,
        string $entityId,
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?string {
        $criteria = $this->buildAssignmentCriteria($entityIdField, $entityId, $context);

        $entity = $repository->search($criteria, $context->getContext())->first();

        if (!$entity instanceof AbstractContentLayoutAssignmentEntity) {
            return null;
        }

        return $entity->getContentLayoutId();
    }

    private function buildAssignmentCriteria(string $entityIdField, string $entityId, SalesChannelContext $context): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        return $criteria;
    }

    private function buildPlaceholderValues(
        string $entityIdField,
        string $entityId,
        Request $request
    ): PlaceholderValues {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

        return PlaceholderValues::from(array_merge(
            [$entityIdField => $entityId],
            $scalarParameters
        ));
    }
}
