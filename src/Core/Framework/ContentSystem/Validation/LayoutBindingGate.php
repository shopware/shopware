<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Shared binding-gate core: loads a content layout's tree by id and returns the binding-scope violations it has
 * against a source's provided root context. The Core entity-assignment gate and the Storefront header/footer gate
 * both delegate here, so the "load tree → resolvability → map" path is single-sourced across sections.
 *
 * @internal
 */
#[Package('framework')]
class LayoutBindingGate
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly LayoutResolvabilityValidator $resolvabilityValidator,
        private readonly ViolationConstraintMapper $violationMapper,
    ) {
    }

    /**
     * @param list<ProvidedContext> $providedRootContext
     */
    public function bindingViolations(mixed $contentLayoutId, array $providedRootContext, Context $context): ConstraintViolationList
    {
        $tree = $this->loadTree($contentLayoutId, $context);

        if ($tree === null) {
            return new ConstraintViolationList();
        }

        $report = $this->resolvabilityValidator->resolvability($tree, $providedRootContext, $context);

        return $this->violationMapper->toConstraintViolationList($report->bindingErrors());
    }

    /**
     * Loads the bound layout's tree from the committed store. Returns null when the layout is not (yet) loadable —
     * either because it does not exist (the FK constraint guards that) or because it is being created in the same
     * uncommitted transaction as this binding (the §8.3 re-check on the next layout edit closes that gap).
     *
     * @return list<ContentElement>|null
     */
    private function loadTree(mixed $contentLayoutId, Context $context): ?array
    {
        if (!\is_string($contentLayoutId) || $contentLayoutId === '') {
            return null;
        }

        $id = \strlen($contentLayoutId) === 16 ? Uuid::fromBytesToHex($contentLayoutId) : $contentLayoutId;

        $layout = $this->definitionRegistry->getRepository(ContentLayoutDefinition::ENTITY_NAME)->search(new Criteria([$id]), $context)->first();

        return $layout instanceof ContentLayoutEntity ? $layout->getLayout() : null;
    }
}
