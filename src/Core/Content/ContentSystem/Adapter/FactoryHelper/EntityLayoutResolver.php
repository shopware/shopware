<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ParameterBinding;
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
 */
#[Package('discovery')]
class EntityLayoutResolver
{
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

    /**
     * Builds placeholder values from entity ID and query parameters.
     *
     * Applies parameter bindings to both sources before merging.
     */
    private function buildPlaceholderValues(
        ContentLayoutAssignmentInterface $assignment,
        string $entityIdField,
        string $entityId,
        Request $request
    ): PlaceholderValues {
        $entityIdPlaceholder = $entityIdField;
        $bindings = $assignment->getParameterBindings();
        if ($bindings !== null && isset($bindings[$entityIdField])) {
            $entityIdPlaceholder = $bindings[$entityIdField]->placeholder ?? $entityIdField;
        }

        $queryParameters = $request->query->all();
        $processedParameters = $this->processQueryParameters($bindings, $queryParameters);

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

    /**
     * Maps query parameter names to placeholder names.
     *
     * Parameters pass through unchanged if no bindings configured.
     *
     * @param array<string, ParameterBinding>|null $bindings
     * @param array<string, mixed> $queryParameters
     *
     * @return array<string, mixed>
     */
    private function processQueryParameters(?array $bindings, array $queryParameters): array
    {
        if ($bindings === null || $bindings === []) {
            return $queryParameters;
        }

        $result = [];
        foreach ($bindings as $paramName => $binding) {
            if (!isset($queryParameters[$paramName])) {
                continue;
            }

            $placeholder = $binding->placeholder ?? $paramName;
            $result[$placeholder] = $queryParameters[$paramName];
        }

        return $result;
    }
}
