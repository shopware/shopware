<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
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
#[Package('discovery')]
class EntityLayoutResolver
{
    public function __construct(private readonly RequestDataExtractor $requestDataExtractor)
    {
    }

    /**
     * Resolves layout assignment and builds placeholder values for entity-based rendering.
     *
     * @template TEntityCollection of CategoryContentLayoutCollection|ProductContentLayoutCollection|LandingPageContentLayoutCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     */
    public function resolve(
        string $entityId,
        Request $request,
        SalesChannelContext $context,
        EntityRepository $repository,
        ContentLayoutAssignableDefinitionInterface $definition
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

        $placeholderValues = $this->buildPlaceholderValues($assignment, $entityIdField, $entityId, $request);

        return new LayoutResolutionResult(
            assignment: $assignment,
            placeholderValues: $placeholderValues
        );
    }

    private function buildPlaceholderValues(
        ContentLayoutAssignmentInterface $assignment,
        string $entityIdField,
        string $entityId,
        Request $request
    ): PlaceholderValues {
        $entityIdPlaceholder = $entityIdField;
        $bindings = $assignment->getParameterBindings();
        if ($bindings !== null && \array_key_exists($entityIdField, $bindings)) {
            $entityIdPlaceholder = $bindings[$entityIdField]->placeholder ?? $entityIdField;
        }

        $processedParameters = $this->requestDataExtractor->extractData($request, $bindings);

        return PlaceholderValues::from(array_merge(
            [$entityIdPlaceholder => $entityId],
            $processedParameters
        ));
    }

    /**
     * Finds layout assignment with sales channel fallback priority: specific → global (null).
     *
     * @template TEntityCollection of CategoryContentLayoutCollection|ProductContentLayoutCollection|LandingPageContentLayoutCollection
     *
     * @param EntityRepository<TEntityCollection> $repository
     */
    private function findLayoutAssignment(
        string $entityIdField,
        string $entityId,
        SalesChannelContext $context,
        EntityRepository $repository
    ): ?ContentLayoutAssignmentInterface {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        $criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $criteria->addAssociation('contentLayout');

        $result = $repository->search($criteria, $context->getContext());

        return $result->first();
    }
}
