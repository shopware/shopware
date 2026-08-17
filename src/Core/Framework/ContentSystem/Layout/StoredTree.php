<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * The forest of stored roots plus the algebra over it. Every edit returns a new forest and leaves the one it
 * was called on untouched.
 *
 * {@see remove()}, {@see replace()} and {@see insertIntoSlot()} walk the forest and rebuild every node the walk
 * visits through {@see StoredElement::withSlots()}, whether or not anything below that node changed; a mutating
 * operation carries over as-is any instance it does not walk. That governs every retention case in this class:
 * the untouched root list on a root-level {@see insertAtRoot()}, which splices without walking at all; the
 * matched parent's existing descendants under {@see insertIntoSlot()}, because the walk stops there; and the
 * caller-supplied replacement subtree {@see replace()} splices in wholesale instead of walking into it.
 *
 * The mutating operations are deliberately checkless: an absent target id or an absent parent id yields an
 * unchanged forest rather than an error. An absent slot name is not in that set — {@see insertIntoSlot()} creates
 * the slot on a parent that does exist and inserts into it. A caller that needs a not-found error establishes
 * existence itself first, through {@see find()} or {@see locate()} — each returns `null` for an unknown id — and
 * raises it before calling the mutating operation.
 *
 * Node-local invariants (numeric wiring keys) throw from the element constructor. Tree-global
 * well-formedness is reported, not thrown: {@see validate()} returns the violations it finds.
 */
#[Package('framework')]
final readonly class StoredTree
{
    /**
     * @param list<StoredElement> $roots
     */
    public function __construct(
        public array $roots,
    ) {
    }

    public function find(string $id): ?StoredElement
    {
        return $this->findIn($this->roots, $id);
    }

    /**
     * The element plus where it sits: its index among its siblings, and the parent element and slot holding
     * it. A root element reports a `null` parent and slot, and then the index is its position in the root list.
     *
     * @return array{element: StoredElement, index: int, parentId: string|null, slot: string|null}|null
     */
    public function locate(string $id): ?array
    {
        foreach ($this->roots as $index => $root) {
            if ($root->id === $id) {
                return ['element' => $root, 'index' => $index, 'parentId' => null, 'slot' => null];
            }
        }

        foreach ($this->roots as $root) {
            $nested = $this->locateUnder($root, $id);

            if ($nested !== null) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Every element id in the forest, in depth-first order, including duplicates.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->collectIds($this->roots);
    }

    public function remove(string $id): self
    {
        return new self($this->removeFrom($this->roots, $id));
    }

    /**
     * A `null`, negative or out-of-range index appends.
     *
     * @param list<StoredElement> $nodes
     */
    public function insertAtRoot(?int $index, array $nodes): self
    {
        return new self($this->splice($this->roots, $index, $nodes));
    }

    /**
     * Inserts into `$parentId`'s `$slot`, creating that slot when the parent does not carry it yet. A
     * `null`, negative or out-of-range index appends.
     *
     * @param list<StoredElement> $nodes
     */
    public function insertIntoSlot(string $parentId, string $slot, ?int $index, array $nodes): self
    {
        return new self($this->insertInto($this->roots, $parentId, $slot, $index, $nodes));
    }

    public function replace(string $id, StoredElement $replacement): self
    {
        return new self($this->replaceIn($this->roots, $id, $replacement));
    }

    /**
     * The tree-global well-formedness report. Ids must be unique across the whole forest, because partial
     * rendering and every mutation address an element by id alone and search all roots for it.
     *
     * An element placed under two parents shows up here as well: it contributes its id twice, so shared
     * ownership is already a duplicate id and needs no separate rule.
     *
     * @return list<Violation>
     */
    public function validate(): array
    {
        $counts = [];
        foreach ($this->ids() as $id) {
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }

        $violations = [];
        foreach ($counts as $id => $count) {
            if ($count < 2) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::DuplicateElementId,
                (string) $id,
                null,
                \sprintf('Element id "%s" is not unique across the layout.', $id),
            );
        }

        return $violations;
    }

    /**
     * @param list<StoredElement> $nodes
     */
    private function findIn(array $nodes, string $id): ?StoredElement
    {
        foreach ($nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }

            foreach ($node->slots as $children) {
                $found = $this->findIn($children, $id);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @return array{element: StoredElement, index: int, parentId: string|null, slot: string|null}|null
     */
    private function locateUnder(StoredElement $parent, string $id): ?array
    {
        foreach ($parent->slots as $slot => $children) {
            foreach ($children as $index => $child) {
                if ($child->id === $id) {
                    return ['element' => $child, 'index' => $index, 'parentId' => $parent->id, 'slot' => $slot];
                }
            }
        }

        foreach ($parent->slots as $children) {
            foreach ($children as $child) {
                $nested = $this->locateUnder($child, $id);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * @param list<StoredElement> $nodes
     *
     * @return list<string>
     */
    private function collectIds(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $ids[] = $node->id;

            foreach ($node->slots as $children) {
                foreach ($this->collectIds($children) as $id) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<StoredElement> $nodes
     *
     * @return list<StoredElement>
     */
    private function removeFrom(array $nodes, string $id): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node->id === $id) {
                continue;
            }

            $out[] = $node->withSlots($this->mapSlots($node, fn (array $children): array => $this->removeFrom($children, $id)));
        }

        return $out;
    }

    /**
     * @param list<StoredElement> $nodes
     * @param list<StoredElement> $inserted
     *
     * @return list<StoredElement>
     */
    private function insertInto(array $nodes, string $parentId, string $slot, ?int $index, array $inserted): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node->id === $parentId) {
                $slots = $node->slots;
                $slots[$slot] = $this->splice($slots[$slot] ?? [], $index, $inserted);
                $out[] = $node->withSlots($slots);

                continue;
            }

            $out[] = $node->withSlots($this->mapSlots($node, fn (array $children): array => $this->insertInto($children, $parentId, $slot, $index, $inserted)));
        }

        return $out;
    }

    /**
     * @param list<StoredElement> $nodes
     *
     * @return list<StoredElement>
     */
    private function replaceIn(array $nodes, string $id, StoredElement $replacement): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if ($node->id === $id) {
                $out[] = $replacement;

                continue;
            }

            $out[] = $node->withSlots($this->mapSlots($node, fn (array $children): array => $this->replaceIn($children, $id, $replacement)));
        }

        return $out;
    }

    /**
     * @param callable(list<StoredElement>): list<StoredElement> $transform
     *
     * @return array<string, list<StoredElement>>
     */
    private function mapSlots(StoredElement $node, callable $transform): array
    {
        $slots = [];

        foreach ($node->slots as $name => $children) {
            $slots[$name] = $transform($children);
        }

        return $slots;
    }

    /**
     * @param list<StoredElement> $list
     * @param list<StoredElement> $nodes
     *
     * @return list<StoredElement>
     */
    private function splice(array $list, ?int $index, array $nodes): array
    {
        if ($index === null || $index < 0 || $index >= \count($list)) {
            return array_values([...$list, ...$nodes]);
        }

        return array_values([...\array_slice($list, 0, $index), ...$nodes, ...\array_slice($list, $index)]);
    }
}
