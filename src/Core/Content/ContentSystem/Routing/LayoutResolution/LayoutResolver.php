<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\LayoutResolution;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentCollection;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutAssignmentEntity;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\ResolvedData;
use Shopware\Core\Content\ContentSystem\Routing\Router\RouteMatchResult;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves layouts via priority-based assignment matching.
 *
 * @internal
 */
#[Package('discovery')]
final class LayoutResolver
{
    /**
     * @param EntityRepository<ContentLayoutAssignmentCollection> $assignmentRepository
     */
    public function __construct(
        private readonly EntityRepository $assignmentRepository,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public function resolve(RouteMatchResult $match, ResolvedData $resolvedData, SalesChannelContext $context): ?string
    {
        $route = $match->route;

        $assignments = $this->loadAssignments($route->getId(), $context);
        if ($assignments->count() === 0) {
            return null;
        }

        /** @var ContentLayoutAssignmentEntity $assignment */
        foreach ($assignments as $assignment) {
            $matches = $assignment->getAssociationPath() === null
                ? $this->matchesDirect($assignment, $resolvedData)
                : $this->matchesAssociation($assignment, $resolvedData, $context);

            if ($matches) {
                return $assignment->getLayoutId();
            }
        }

        return null;
    }

    /**
     * @return EntityCollection<ContentLayoutAssignmentEntity>
     */
    private function loadAssignments(string $routeId, SalesChannelContext $context): EntityCollection
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('routeId', $routeId));
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
            new EqualsFilter('salesChannelId', null),
        ]));

        // Priority DESC, then channel-specific over global
        $criteria->addSorting(
            new FieldSorting('priority', FieldSorting::DESCENDING),
            new FieldSorting('salesChannelId', FieldSorting::DESCENDING)
        );

        return $this->assignmentRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function matchesDirect(ContentLayoutAssignmentEntity $assignment, ResolvedData $data): bool
    {
        // Route-level or default assignment (entity_type = NULL)
        if ($assignment->getEntityType() === null) {
            return true;
        }

        $resolvedId = $data->getEntityId($assignment->getEntityType() . '_id')
                   ?? $data->getEntityId($assignment->getEntityType());

        if ($resolvedId === null) {
            return false;
        }

        // Wildcard (entity_id = NULL, matches any entity of this type)
        if ($assignment->getEntityId() === null) {
            return true;
        }

        return $resolvedId === $assignment->getEntityId();
    }

    private function matchesAssociation(ContentLayoutAssignmentEntity $assignment, ResolvedData $data, SalesChannelContext $context): bool
    {
        $path = $assignment->getAssociationPath();
        if ($path === null) {
            return false;
        }

        // Example: 'product.categories.parent' splits to source='product', path='categories.parent'
        $parts = explode('.', $path);
        if (\count($parts) < 2) {
            return false;
        }

        $sourceEntity = array_shift($parts);
        $associationPath = implode('.', $parts);

        $sourceId = $data->getEntityId($sourceEntity . '_id') ?? $data->getEntityId($sourceEntity);
        if ($sourceId === null) {
            return false;
        }

        $associatedIds = $this->loadNestedAssociation($sourceEntity, $sourceId, $associationPath, $parts, $context);
        if (empty($associatedIds)) {
            return false;
        }

        if ($assignment->getEntityId() !== null) {
            return \in_array($assignment->getEntityId(), $associatedIds, true);
        }

        // Wildcard - any associated entity matches
        return true;
    }

    /**
     * Supports multi-level association paths (e.g., 'categories.parent.children').
     * Handles collections and single entities at any level.
     *
     * @param list<string> $pathParts
     *
     * @return list<string>
     */
    private function loadNestedAssociation(
        string $sourceEntity,
        string $sourceId,
        string $associationPath,
        array $pathParts,
        SalesChannelContext $context
    ): array {
        $criteria = new Criteria([$sourceId]);
        $criteria->addAssociation($associationPath);

        $associationCriteria = $criteria->getAssociation($associationPath);
        $associationCriteria->addFields(['id']);

        $definition = $this->definitionRegistry->getByEntityName($sourceEntity);
        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

        $entity = $repository->search($criteria, $context->getContext())->first();
        if (!$entity instanceof Entity) {
            return [];
        }

        return $this->traverseAndCollectIds($entity, $pathParts);
    }

    /**
     * Handles collections at any level (flattens), single entities (recurses), and leaf nodes (returns ID).
     *
     * Example for 'categories.parent':
     * - product.categories → [cat1, cat2, cat3]
     * - Each category.parent → parent entities
     * - Result: [parent1Id, parent2Id, ...]
     *
     * @param list<string> $remainingParts
     *
     * @return list<string>
     */
    private function traverseAndCollectIds(Entity $entity, array $remainingParts): array
    {
        if (empty($remainingParts)) {
            return [$entity->getUniqueIdentifier()];
        }

        $part = array_shift($remainingParts);
        if (!$entity->has($part) || $entity->get($part) === null) {
            return [];
        }

        $value = $entity->get($part);

        if ($value instanceof EntityCollection) {
            $ids = [];
            foreach ($value as $item) {
                $ids = array_merge($ids, $this->traverseAndCollectIds($item, $remainingParts));
            }

            return array_values(array_unique($ids));
        }

        if ($value instanceof Entity) {
            return $this->traverseAndCollectIds($value, $remainingParts);
        }

        return [];
    }
}
