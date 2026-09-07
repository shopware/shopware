<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

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
 * A {@see ConsumerScope::Root} consumer is filled from the ambient map argument to {@see resolve()}, not by
 * the walk, so it receives at any depth with no intermediate wiring.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContextDeliveryResolver
{
    public function __construct(
        private ContextDistributor $distributor,
        private ContextPathResolver $pathResolver,
    ) {
    }

    /**
     * `$loaderValues` is keyed by element id, then by requirement key — the shape
     * {@see ElementDataResolver::resolve()} returns per element, collected for the forest. It arrives
     * precomputed rather than being resolved here because loading completes for the whole forest before any
     * distribution starts: a provider may hand on a loaded value, so every load must already have happened
     * by the time the first parent distributes. An element with no entry simply has no loader values, which
     * is the ordinary case and not an error.
     *
     * `$ambientContext` is the layout's root-ambient map, resolved once per render by {@see ElementLowering}
     * and passed in so root delivery never depends on tree shape. An empty map (SKELETON, no wrapper) delivers
     * nothing and is never a signal to fall back to a tree lookup.
     *
     * @param list<StoredElement> $forest roots in order
     * @param array<string, array<string, mixed>> $loaderValues element id => requirement key => resolved value
     * @param array<string, mixed> $ambientContext root-ambient values, keyed by page-level requirement key
     *
     * @throws ContentSystemException when a required consumer's path cannot be resolved
     */
    public function resolve(array $forest, array $loaderValues, array $ambientContext): ContextDeliveryIndex
    {
        $deliveries = [];

        foreach ($forest as $root) {
            $this->walk($root, $loaderValues, $ambientContext, new ContextDelivery($root->id), $deliveries);
        }

        return new ContextDeliveryIndex($deliveries);
    }

    /**
     * The root-scoped overlay runs FIRST, before the working map is read: a root-delivered value must be in
     * the working map for the element's own providers to hand it on, exactly as a parent-delivered one is.
     * Records what this element received before descending, so a parent's entry is always in place before its
     * children's.
     *
     * @param array<string, array<string, mixed>> $loaderValues
     * @param array<string, mixed> $ambientContext
     * @param array<string, ContextDelivery> $deliveries
     */
    private function walk(
        StoredElement $element,
        array $loaderValues,
        array $ambientContext,
        ContextDelivery $delivery,
        array &$deliveries,
    ): void {
        $delivery = $this->overlayRootContext($element, $ambientContext, $delivery);

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
            $this->walk($child, $loaderValues, $ambientContext, $childDeliveries[$index], $deliveries);
        }
    }

    /**
     * Fills this element's root-scoped consumers from the ambient map, on top of what its parent delivered.
     *
     * Matching, dot-path resolution, and the delivered-under key (`propertyAlias ?? consumerKey`) follow the
     * parent-delivery rules ({@see ContextDistributor}), against ambient keys instead of provider keys.
     *
     * An ambient `null` delivers nothing and writes no key, matching the provider null gate in
     * {@see ContextDistributor::distribute()}: a key absent from a delivery is one nothing delivered, and an
     * ambient null must not be turned into the present null that means a resolution ran and found nothing.
     *
     * Root-scoped writes run after the parent's, so they win a shared property key. Nothing can produce that
     * collision today (`WiringPlanner::validatePropertyAliases()` makes the base keys an element's consumers
     * write unique across both scopes), so the order is defensive rather than a rule anything relies on.
     *
     * @param array<string, mixed> $ambientContext
     */
    private function overlayRootContext(
        StoredElement $element,
        array $ambientContext,
        ContextDelivery $delivery,
    ): ContextDelivery {
        if ($ambientContext === []) {
            return $delivery;
        }

        $context = $delivery->context;
        $overlaid = false;

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if ($consumer->scope !== ConsumerScope::Root) {
                continue;
            }

            foreach ($ambientContext as $ambientKey => $value) {
                if ($value === null || !$this->pathResolver->matches($ambientKey, $consumerKey)) {
                    continue;
                }

                $context[$consumer->propertyAlias ?? $consumerKey] = $this->ambientValueFor(
                    $element,
                    $consumerKey,
                    $consumer,
                    $ambientKey,
                    $value
                );
                $overlaid = true;
            }
        }

        return $overlaid ? $delivery->withContext($context) : $delivery;
    }

    /**
     * An exact match hands on the SAME PHP instance, which is what makes the value-index instance map collapse
     * a root delivery onto the ambient loader value's ref. A dot path resolves through the value, which needs
     * a {@see Struct} to traverse: a required consumer that cannot get one fails naming this element, an
     * optional one takes an explicit null.
     */
    private function ambientValueFor(
        StoredElement $element,
        string $consumerKey,
        ContextConsumer $consumer,
        string $ambientKey,
        mixed $data,
    ): mixed {
        if ($consumerKey === $ambientKey) {
            return $data;
        }

        if (!$data instanceof Struct) {
            if ($consumer->required) {
                throw ContentSystemException::contextPathNotResolvable(
                    $consumerKey,
                    $element->id,
                    'Context data is not a Struct instance'
                );
            }

            return null;
        }

        return $this->pathResolver->resolvePath(
            $data,
            $this->pathResolver->parseContextKey($consumerKey),
            $consumer->required,
            $consumerKey,
            $element->id
        );
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
