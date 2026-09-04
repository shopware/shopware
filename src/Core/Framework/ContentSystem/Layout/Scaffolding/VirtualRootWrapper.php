<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles virtual root wrapping and unwrapping for page-level context distribution.
 *
 * Virtual root is a temporary structural modification (scaffolding) that wraps actual layout
 * roots to enable page-level data requirements to be distributed as broadcast context.
 *
 * Every method here is typed against one of the two split models, matching where in the pipeline it runs.
 * `requiresWrapping()`, `wrap()` and `isVirtualRoot()` take {@see StoredElement}: {@see StoredTreePreparer}
 * calls them while it holds the storage model — the wrap before the lowering, the identity check on the
 * post-prune stored forest. `unwrap()` takes {@see RenderedElement}, because the pipeline reaches it after
 * the render step, as one of the finishing steps on the rendered forest.
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
     * @param list<StoredElement> $elements
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
     * The placeholder values arrive as raw scalars and are wrapped here, which makes this one of the
     * sanctioned {@see StoredValue} mint sites. Their keys can never be numeric: `PlaceholderValues::from()`
     * rejects a non-string key, and PHP casts a numeric-string key to an int before it gets there.
     *
     * @param list<StoredElement> $actualRoots
     */
    public function wrap(array $actualRoots, RenderingSpecification $specification): StoredElement
    {
        return new StoredElement(
            self::VIRTUAL_ROOT_ID,
            self::VIRTUAL_ROOT_TYPE,
            $this->indexDataRequirements($specification->dataRequirements),
            array_map(StoredValue::fromDecoded(...), $specification->placeholderValues->all()),
            [self::VIRTUAL_ROOT_SLOT_NAME => $actualRoots],
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
     * @return non-empty-list<RenderedElement>
     */
    public function unwrap(RenderedElement $virtualRoot): array
    {
        $pageRootsSlot = $virtualRoot->slots[self::VIRTUAL_ROOT_SLOT_NAME] ?? null;
        $extractedRoots = $pageRootsSlot === null ? [] : array_values($pageRootsSlot);

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
    public function isVirtualRoot(StoredElement $element): bool
    {
        return $element->id === self::VIRTUAL_ROOT_ID;
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
