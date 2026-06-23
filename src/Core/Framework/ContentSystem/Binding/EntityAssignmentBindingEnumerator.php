<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Enumerates the Core entity-assignment bindings (product/category/landing-page) of a content layout. The
 * requirement set is type-level, so it emits one binding per registered assignment-definition type
 * that has any row referencing the layout — bounded by distinct assigned types, not by assignment rows.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityAssignmentBindingEnumerator implements LayoutBindingEnumerator
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RootContextMapper $rootContextMapper,
    ) {
    }

    public function enumerate(string $contentLayoutId, Context $context): array
    {
        $bindings = [];

        foreach ($this->definitionRegistry->getDefinitions() as $definition) {
            if (!$definition instanceof AbstractContentLayoutAssignableDefinition) {
                continue;
            }

            if (!$this->hasAssignment($definition, $contentLayoutId, $context)) {
                continue;
            }

            $bindings[] = new SourceBinding(
                $definition->getContentLayoutEntityType(),
                $this->rootContextMapper->map($definition->getPageDataRequirements()),
            );
        }

        return $bindings;
    }

    private function hasAssignment(AbstractContentLayoutAssignableDefinition $definition, string $contentLayoutId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('contentLayoutId', $contentLayoutId));
        $criteria->setLimit(1);

        $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

        return $repository->searchIds($criteria, $context)->firstId() !== null;
    }
}
