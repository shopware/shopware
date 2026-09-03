<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;

/**
 * Answers, for a whole stored forest, what context every element received. It walks the forest pre-order and
 * calls {@see ContextDistributor::distribute()} at each parent, and returns a {@see ContextDeliveryIndex};
 * it writes into no element, and the elements it walks come back untouched.
 *
 * The walk MUST run top-down, and that is a correctness requirement rather than a preference. The working
 * map a parent distributes from includes the context that parent itself received, so a container that
 * receives context and re-provides it passes on what it was given. Process a child before its parent and the
 * second hop of such a chain distributes from a map that is missing the first hop's value.
 *
 * The working map is stored values, then loader values, then received context, in
 * {@see RenderedElementFactory} precedence order — delivered context over loader-resolved over declared. It
 * is the FULL stored map, unfiltered: an undeclared stored key is a legitimate provider source, and
 * filtering to the rendered union first would make it quietly stop delivering with nothing raising.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContextDeliveryResolver
{
    public function __construct(
        private ContextDistributor $distributor,
    ) {
    }

    /**
     * `$loaderValues` is keyed by element id, then by requirement key — the shape
     * {@see ElementDataResolver::resolve()} returns per element, collected for the forest. It arrives
     * precomputed rather than being resolved here. The depth-first rendering path instead uses
     * {@see self::resolveDirectChildren()} after loading each parent, allowing child loaders to consume the
     * delivered context. An element with no entry simply has no loader values, which is the ordinary case and
     * not an error.
     *
     * @param list<StoredElement> $forest roots in order
     * @param array<string, array<string, mixed>> $loaderValues element id => requirement key => resolved value
     *
     * @throws ContentSystemException when a required consumer's path cannot be resolved
     */
    public function resolve(array $forest, array $loaderValues): ContextDeliveryIndex
    {
        $deliveries = [];

        foreach ($forest as $root) {
            $this->walk($root, $loaderValues, new ContextDelivery($root->id), $deliveries);
        }

        return new ContextDeliveryIndex($deliveries);
    }

    /**
     * Resolves the context delivered by one element to its direct children.
     *
     * This is used by the depth-first rendering walk so a child's data loaders can consume
     * context provided by its parent.
     *
     * @param array<string, mixed> $loaderValues
     * @param array<string, mixed> $receivedContext
     *
     * @return list<ContextDelivery>
     */
    public function resolveDirectChildren(
        StoredElement $element,
        array $loaderValues,
        array $receivedContext = [],
    ): array {
        $children = $this->childrenInDeliveryOrder($element);

        if ($children === []) {
            return [];
        }

        return $this->distributor->distribute(
            $element,
            $this->workingValues($element, [$element->id => $loaderValues], $receivedContext),
            $children,
        );
    }

    /**
     * Records what this element received before descending, so the index is filled in the same pre-order the
     * distribution runs in and a parent's entry is always in place before its children's.
     *
     * @param array<string, array<string, mixed>> $loaderValues
     * @param array<string, ContextDelivery> $deliveries
     */
    private function walk(
        StoredElement $element,
        array $loaderValues,
        ContextDelivery $delivery,
        array &$deliveries,
    ): void {
        $deliveries[$element->id] = $delivery;

        $children = $this->childrenInDeliveryOrder($element);

        if ($children === []) {
            return;
        }

        $childDeliveries = $this->distributor->distribute(
            $element,
            $this->workingValues($element, $loaderValues, $delivery->context),
            $children
        );

        foreach ($children as $index => $child) {
            $this->walk($child, $loaderValues, $childDeliveries[$index], $deliveries);
        }
    }

    /**
     * Slot-then-index order: every child of the first slot in declaration order, then every child of the
     * next. {@see ContextDistributor} hands indexed and sliced positions out against this sequence and
     * cannot check it, so producing it correctly is this class's job — a different order silently gives a
     * different child a different item rather than failing.
     *
     * @return list<StoredElement>
     */
    private function childrenInDeliveryOrder(StoredElement $element): array
    {
        return array_merge([], ...array_values($element->slots));
    }

    /**
     * Later writes win, which is what puts delivered context over loader-resolved over declared. Each tier
     * is written key by key rather than merged wholesale, so a loader's explicit null and a delivered null
     * both land as present values instead of being skipped.
     *
     * @param array<string, array<string, mixed>> $loaderValues
     * @param array<string, mixed> $received
     *
     * @return array<string, mixed>
     */
    private function workingValues(StoredElement $element, array $loaderValues, array $received): array
    {
        $values = array_map(
            static fn (StoredValue $value): mixed => $value->jsonSerialize(),
            $element->properties()
        );

        foreach ($loaderValues[$element->id] ?? [] as $key => $value) {
            $values[$key] = $value;
        }

        foreach ($received as $key => $value) {
            $values[$key] = $value;
        }

        return $values;
    }
}
