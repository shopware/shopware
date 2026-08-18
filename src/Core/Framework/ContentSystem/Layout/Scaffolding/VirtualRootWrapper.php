<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles virtual root wrapping and unwrapping for page-level context distribution.
 *
 * Virtual root is a temporary structural modification (scaffolding) that wraps actual layout
 * roots to enable page-level data requirements to be distributed as broadcast context.
 *
 * @internal
 */
#[Package('framework')]
final class VirtualRootWrapper
{
    public const VIRTUAL_ROOT_ID = '__page_context_root__';
    private const VIRTUAL_ROOT_TYPE = 'Sw:Internal:PageContext';
    private const VIRTUAL_ROOT_SLOT_NAME = '__page_roots__';

    /**
     * Determines if virtual root wrapping is required.
     *
     * Virtual root is needed when page-level data requirements exist and layout has content roots to wrap.
     *
     * @param array<ContentElement> $elements
     */
    public function requiresWrapping(RenderingSpecification $specification, array $elements): bool
    {
        if ($specification->dataRequirements === []) {
            return false;
        }

        if ($elements === []) {
            return false;
        }

        return true;
    }

    /**
     * Creates virtual root wrapper containing actual layout roots.
     *
     * Virtual root contains layout-level data requirements, exposes loaded data
     * as broadcast context providers, and has actual roots as children in a single slot.
     *
     * @param array<ContentElement> $actualRoots
     */
    public function wrap(array $actualRoots, RenderingSpecification $specification): ContentElement
    {
        return new ContentElement(
            self::VIRTUAL_ROOT_ID,
            self::VIRTUAL_ROOT_TYPE,
            $this->indexDataRequirements($specification->dataRequirements),
            $specification->placeholderValues->all(),
            [self::VIRTUAL_ROOT_SLOT_NAME => new SlotContent($actualRoots)],
            $this->createContextDefinitions($specification->dataRequirements)
        );
    }

    /**
     * Extracts the actual roots back out of the virtual root wrapper.
     *
     * The caller establishes that this element is the wrapper, so identity is not re-checked here.
     * A wrapper always holds at least one root — `requiresWrapping()` refuses an empty forest and the
     * partial prune rebuilds the slot around the surviving child — so a wrapper whose roots slot is
     * absent or empty is a corrupt tree, never an empty layout, and is rejected rather than reported
     * as no roots.
     *
     * @throws ContentSystemException If the roots slot holds no roots
     *
     * @return non-empty-list<ContentElement>
     */
    public function unwrap(ContentElement $virtualRoot): array
    {
        $pageRootsSlot = $virtualRoot->getSlots()[self::VIRTUAL_ROOT_SLOT_NAME] ?? null;
        $extractedRoots = $pageRootsSlot === null ? [] : array_values($pageRootsSlot->getElements());

        if ($extractedRoots === []) {
            throw ContentSystemException::invalidMapValue(
                'Virtual page context root slot map',
                self::VIRTUAL_ROOT_SLOT_NAME,
                'a slot holding at least one root',
                $pageRootsSlot === null ? 'no such slot' : 'an empty slot'
            );
        }

        return $extractedRoots;
    }

    /**
     * Checks if element is the virtual root wrapper.
     */
    public function isVirtualRoot(ContentElement $element): bool
    {
        return $element->getId() === self::VIRTUAL_ROOT_ID;
    }

    /**
     * Index data requirements by key for O(1) lookups.
     *
     * @param array<DataRequirement> $requirements
     *
     * @return array<string, DataRequirement>
     */
    private function indexDataRequirements(array $requirements): array
    {
        $indexed = [];

        foreach ($requirements as $requirement) {
            $indexed[$requirement->key] = $requirement;
        }

        return $indexed;
    }

    /**
     * Create broadcast providers for layout-level data requirements.
     *
     * @param array<DataRequirement> $layoutDataRequirements
     */
    private function createContextDefinitions(array $layoutDataRequirements): ContextDefinitions
    {
        $providers = [];

        foreach ($layoutDataRequirements as $requirement) {
            $providers[$requirement->key] = new ContextProvider(
                ContextType::Single,
                BroadcastDistributionConfig::simple()
            );
        }

        return new ContextDefinitions($providers, []);
    }
}
