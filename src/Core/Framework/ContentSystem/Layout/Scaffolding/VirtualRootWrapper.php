<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Wraps a layout's roots in one synthetic root before the render step and takes it off again after, a
 * temporary structural modification (scaffolding) the stored forest carries only while it is being rendered.
 *
 * The wrapper has two roles and no third. It CARRIES the page-level placeholder values, so the ambient
 * resolution of the page-level data requirements has an element to resolve its loader inputs against; and it
 * is the SCAFFOLD the wrap/unwrap pair unwinds, which is what gives a multi-root layout a single node the
 * partial prune can keep. It is minted with no data requirements and no context definitions, so it loads
 * nothing and distributes nothing to the roots in its slot: root-ambient context reaches an element through
 * its own root-scoped consumers instead, at any depth ({@see ContextDeliveryResolver}).
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
     * Whether the render wraps at all: page-level data requirements exist AND the layout has roots to wrap.
     *
     * Both halves are load-bearing, and neither is about distribution. Page-level requirements resolve their
     * loader inputs against the placeholder values this wrapper carries, so with no requirements there is
     * nothing to carry them for; with no roots there is nothing to wrap around and no forest to scaffold.
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
     * Creates the virtual root wrapper holding the actual layout roots in a single slot.
     *
     * It carries the placeholder values and nothing else — an empty data-requirement map and empty context
     * definitions — so the page-level requirements load exactly once, through the ambient path, and no value
     * is broadcast from here to the roots underneath.
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
            [],
            array_map(StoredValue::fromDecoded(...), $specification->placeholderValues->all()),
            [self::VIRTUAL_ROOT_SLOT_NAME => $actualRoots],
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
}
