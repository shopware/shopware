<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Shared structural machinery for the layout mutations: immutable path-copying tree surgery (every transform
 * reconstructs nodes through the ContentElement constructor), the affected/orphaned/droppedWiring stash the
 * pipeline reads after {@see apply()}, fresh-element scaffolding, and the uniform 400 for structural
 * impossibilities.
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
     * @var list<ContentElement>
     */
    protected array $orphaned = [];

    /**
     * @var list<string>
     */
    protected array $droppedWiring = [];

    /**
     * @var array<string, mixed>
     */
    protected array $droppedProperties = [];

    private ?PrimitiveDefaultProvider $primitiveDefaultProvider = null;

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
     * @param list<ContentElement> $tree
     */
    protected function findNode(array $tree, string $id): ?ContentElement
    {
        foreach ($tree as $node) {
            if ($node->getId() === $id) {
                return $node;
            }

            $found = $this->findNode($this->childList($node), $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param list<ContentElement> $tree
     */
    protected function locate(array $tree, string $id): ?ElementLocation
    {
        foreach (array_values($tree) as $index => $node) {
            if ($node->getId() === $id) {
                return new ElementLocation($node, $index);
            }
        }

        foreach ($tree as $node) {
            $nested = $this->locateInParent($node, $id);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @return list<string> the id of $node plus every descendant id
     */
    protected function subtreeIds(ContentElement $node): array
    {
        $ids = [$node->getId()];

        foreach ($this->childList($node) as $child) {
            foreach ($this->subtreeIds($child) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<ContentElement> $tree
     *
     * @return list<ContentElement>
     */
    protected function removeSubtree(array $tree, string $id): array
    {
        $out = [];

        foreach ($tree as $node) {
            if ($node->getId() === $id) {
                continue;
            }

            $out[] = $this->rebuildNode($node, $this->mapSlots($node, fn (array $children): array => $this->removeSubtree($children, $id)));
        }

        return $out;
    }

    /**
     * @param list<ContentElement> $tree
     * @param list<ContentElement> $nodes
     *
     * @return list<ContentElement>
     */
    protected function insertAtRoot(array $tree, ?int $index, array $nodes): array
    {
        return $this->spliceList($tree, $index, $nodes);
    }

    /**
     * @param list<ContentElement> $tree
     * @param list<ContentElement> $nodes
     *
     * @return list<ContentElement>
     */
    protected function insertIntoSlot(array $tree, string $parentId, string $slot, ?int $index, array $nodes): array
    {
        $out = [];

        foreach ($tree as $node) {
            if ($node->getId() === $parentId) {
                $out[] = $this->spliceIntoNodeSlot($node, $slot, $index, $nodes);

                continue;
            }

            $out[] = $this->rebuildNode($node, $this->mapSlots($node, fn (array $children): array => $this->insertIntoSlot($children, $parentId, $slot, $index, $nodes)));
        }

        return $out;
    }

    /**
     * @param list<ContentElement> $tree
     *
     * @return list<ContentElement>
     */
    protected function replaceNode(array $tree, string $id, ContentElement $replacement): array
    {
        $out = [];

        foreach ($tree as $node) {
            if ($node->getId() === $id) {
                $out[] = $replacement;

                continue;
            }

            $out[] = $this->rebuildNode($node, $this->mapSlots($node, fn (array $children): array => $this->replaceNode($children, $id, $replacement)));
        }

        return $out;
    }

    protected function cloneWithNewIds(ContentElement $node): ContentElement
    {
        return new ContentElement(
            Uuid::randomHex(),
            $node->getComponent(),
            $node->getDataRequirements(),
            $node->getProperties(),
            $this->mapSlots($node, fn (array $children): array => array_values(array_map($this->cloneWithNewIds(...), $children))),
            $node->getContextDefinitions(),
            $node->getStyle(),
            $node->getAttributedSpecifications(),
        );
    }

    /**
     * @param array<string, SlotContent> $slots
     */
    protected function scaffoldElement(AbstractContentSystemElementTypeRegistry $registry, string $type, array $slots = []): ContentElement
    {
        return new ContentElement(Uuid::randomHex(), $type, [], $this->primitiveDefaults($registry, $type), $slots);
    }

    /**
     * The type's primitive property defaults to seed into a stored element, keyed by property key. The single rule
     * lives in {@see PrimitiveDefaultProvider}, shared with the write-boundary seeder so a type's defaults are
     * defined once. The provider is stateless; a mutation is not a DI service, so it is instantiated once per
     * mutation instance and memoized rather than injected.
     *
     * @return array<string, string|int|float|bool>
     */
    protected function primitiveDefaults(AbstractContentSystemElementTypeRegistry $registry, string $type): array
    {
        return ($this->primitiveDefaultProvider ??= new PrimitiveDefaultProvider())->forType($registry, $type);
    }

    protected function requireRegistered(AbstractContentSystemElementTypeRegistry $registry, string $type): void
    {
        if ($registry->has($type)) {
            return;
        }

        throw ContentSystemException::mutationUnknownType($type);
    }

    /**
     * @return list<ContentElement> every direct child of $node across all its slots, in slot order
     */
    protected function childList(ContentElement $node): array
    {
        $children = [];

        foreach ($node->getSlots() as $slotContent) {
            foreach (array_values($slotContent->getElements()) as $child) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function locateInParent(ContentElement $parent, string $id): ?ElementLocation
    {
        foreach ($parent->getSlots() as $slotName => $slotContent) {
            foreach (array_values($slotContent->getElements()) as $index => $child) {
                if ($child->getId() === $id) {
                    return new ElementLocation($child, $index, new ParentSlot($parent->getId(), $slotName));
                }
            }
        }

        foreach ($this->childList($parent) as $child) {
            $nested = $this->locateInParent($child, $id);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * @param list<ContentElement> $nodes
     */
    private function spliceIntoNodeSlot(ContentElement $parent, string $slot, ?int $index, array $nodes): ContentElement
    {
        $slots = $parent->getSlots();
        $existing = isset($slots[$slot]) ? array_values($slots[$slot]->getElements()) : [];
        $slots[$slot] = new SlotContent($this->spliceList($existing, $index, $nodes));

        return $this->rebuildNode($parent, $slots);
    }

    /**
     * @param list<ContentElement> $list
     * @param list<ContentElement> $nodes
     *
     * @return list<ContentElement>
     */
    private function spliceList(array $list, ?int $index, array $nodes): array
    {
        if ($index === null || $index < 0 || $index >= \count($list)) {
            return array_values([...$list, ...$nodes]);
        }

        return array_values([...\array_slice($list, 0, $index), ...$nodes, ...\array_slice($list, $index)]);
    }

    /**
     * @param callable(list<ContentElement>): list<ContentElement> $transform
     *
     * @return array<string, SlotContent>
     */
    private function mapSlots(ContentElement $node, callable $transform): array
    {
        $slots = [];

        foreach ($node->getSlots() as $name => $slotContent) {
            $slots[$name] = new SlotContent($transform(array_values($slotContent->getElements())));
        }

        return $slots;
    }

    /**
     * @param array<string, SlotContent> $newSlots
     */
    private function rebuildNode(ContentElement $node, array $newSlots): ContentElement
    {
        return new ContentElement(
            $node->getId(),
            $node->getComponent(),
            $node->getDataRequirements(),
            $node->getProperties(),
            $newSlots,
            $node->getContextDefinitions(),
            $node->getStyle(),
            $node->getAttributedSpecifications(),
        );
    }
}
