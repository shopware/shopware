<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;

/**
 * One structural layout edit. The pipeline calls {@see apply()} first, then reads {@see affected()} and
 * {@see orphaned()}; both reflect the change computed during {@see apply()}, so an op is single-use and
 * {@see apply()} must run before either is read.
 *
 * @internal
 */
#[Package('framework')]
interface LayoutMutation
{
    /**
     * Pure transform: returns a NEW forest. {@see StoredTree} and {@see StoredElement} are both immutable, so
     * "does not mutate the input" is a property of the types rather than a discipline this contract asks for.
     */
    public function apply(StoredTree $tree): StoredTree;

    /**
     * @return list<string> element ids whose resolution may have changed (a conservative highlight hint; the
     *                      authoritative correctness output is the pipeline's full diagnostics pass)
     */
    public function affected(): array;

    /**
     * @return list<StoredElement> subtrees detached by the op (e.g. replace dropping a slot's children),
     *                             returned so the caller can re-place them; never discarded
     */
    public function orphaned(): array;

    /**
     * @return list<string> wiring keys the op dropped because they no longer fit (e.g. replace to a type
     *                      without that reference property), reported so the caller can re-wire; never
     *                      silently altered
     */
    public function droppedWiring(): array;

    /**
     * @return array<string, StoredValue> static property values the op could not carry over to the new type
     *                                    (e.g. replace to a type lacking that property, or whose property type
     *                                    rejects the value), keyed by property key, reported so authored
     *                                    content is never silently lost
     */
    public function droppedProperties(): array;
}
