<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The mutation-side helpers that {@see StoredTree} does not carry: the report stash, the typed view over
 * {@see StoredTree::locate()}, and the element-level primitives the ops share. The tree algebra itself —
 * find, remove, insert, replace — lives on {@see StoredTree} and is called there directly.
 *
 * @internal
 */
#[Package('framework')]
abstract class AbstractLayoutMutation implements LayoutMutation
{
    /**
     * @var list<string>
     */
    protected array $affected = [];

    /**
     * @var list<StoredElement>
     */
    protected array $orphaned = [];

    /**
     * @var list<string>
     */
    protected array $droppedWiring = [];

    /**
     * @var array<string, StoredValue>
     */
    protected array $droppedProperties = [];

    public function affected(): array
    {
        return $this->affected;
    }

    public function orphaned(): array
    {
        return $this->orphaned;
    }

    public function droppedWiring(): array
    {
        return $this->droppedWiring;
    }

    public function droppedProperties(): array
    {
        return $this->droppedProperties;
    }

    /**
     * The typed view of {@see StoredTree::locate()}: the same coordinates, as objects the ops can destructure
     * by name. The array the tree returns stays the single source; this adds no rule of its own.
     */
    protected function locate(StoredTree $tree, string $id): ?ElementLocation
    {
        $located = $tree->locate($id);

        if ($located === null) {
            return null;
        }

        $parentId = $located['parentId'];
        $slot = $located['slot'];

        // The tree reports both coordinates or neither: a root carries no parent and no slot.
        if ($parentId === null || $slot === null) {
            return new ElementLocation($located['element'], $located['index']);
        }

        return new ElementLocation($located['element'], $located['index'], new ParentSlot($parentId, $slot));
    }

    /**
     * The id of $node plus every descendant id. {@see StoredTree::ids()} is forest-scoped and emits a node's id
     * ahead of each slot's children depth-first, which is exactly this order, so a one-node forest reproduces
     * it. Kept as one helper rather than repeated at every call site: wrapping a node in a throwaway tree is an
     * idiom, not a rename.
     *
     * @return list<string>
     */
    protected function subtreeIds(StoredElement $node): array
    {
        return (new StoredTree([$node]))->ids();
    }

    /**
     * Deep copy of $node with a freshly minted id on every node in it. The copiers carry every field the clone
     * keeps, so only the id and the rebuilt slots are named here.
     */
    protected function cloneWithNewIds(StoredElement $node): StoredElement
    {
        $slots = [];

        foreach ($node->slots as $name => $children) {
            $slots[$name] = array_values(array_map($this->cloneWithNewIds(...), $children));
        }

        return $node->withId(Uuid::randomHex())->withSlots($slots);
    }

    /**
     * @param array<string, list<StoredElement>> $slots
     */
    protected function scaffoldElement(AbstractContentSystemElementTypeRegistry $registry, string $type, array $slots = []): StoredElement
    {
        return new StoredElement(Uuid::randomHex(), $type, [], $this->primitiveDefaults($registry, $type), $slots);
    }

    /**
     * The type's primitive property defaults to seed into a stored element, keyed by property key and wrapped
     * for storage. The single rule lives in {@see PrimitiveDefaultProvider}, shared with the write-boundary
     * seeder so a type's defaults are defined once; the wrapping is applied here, at the one place a mutation
     * puts a raw default into a stored element.
     *
     * @return array<string, StoredValue>
     */
    protected function primitiveDefaults(AbstractContentSystemElementTypeRegistry $registry, string $type): array
    {
        $defaults = (new PrimitiveDefaultProvider())->forType($registry, $type);

        return array_map(StoredValue::fromDecoded(...), $defaults);
    }

    protected function requireRegistered(AbstractContentSystemElementTypeRegistry $registry, string $type): void
    {
        if ($registry->has($type)) {
            return;
        }

        throw ContentSystemException::mutationUnknownType($type);
    }

    /**
     * The type's default binding specification (`byType($type)` filtered by `isDefault()`), read as zero, one, or
     * more: zero returns null (nothing to fill-apply), one is returned, more than one throws — never a first-wins
     * pick. Shared by {@see InsertElement} and
     * {@see ReplaceElement}, the two ops that auto-apply a
     * type's default at scaffold.
     */
    protected function resolveDefaultSpecification(AbstractContentSystemBindingSpecificationRegistry $bindingRegistry, string $type): ?BindingSpecification
    {
        $defaults = array_values(array_filter(
            $bindingRegistry->byType($type),
            static fn (BindingSpecification $specification): bool => $specification->isDefault(),
        ));

        if ($defaults === []) {
            return null;
        }

        if (\count($defaults) === 1) {
            return $defaults[0];
        }

        throw ContentSystemException::bindingSpecificationDefaultAmbiguous(
            $type,
            array_map(static fn (BindingSpecification $specification): string => $specification->qualifiedId(), $defaults),
        );
    }

    /**
     * @return list<StoredElement> every direct child of $node across all its slots, in slot order
     */
    protected function childList(StoredElement $node): array
    {
        $children = [];

        foreach ($node->slots as $slotChildren) {
            foreach ($slotChildren as $child) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
