<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * The context rule for ONE parent and its direct children, on the storage model. It walks nothing: given a
 * parent and the children it already has, it computes what each of them receives and returns that. Whoever
 * owns the traversal calls this per parent.
 *
 * It writes into no child: the providers, the consumer matching, the five distribution strategies and the
 * throws all resolve here, and what each child is to receive comes back as a {@see ContextDelivery} for the
 * caller to mint from.
 *
 * Order decides two things, and both come from the arguments rather than from anything derived here:
 *
 * - Provider order is `ContextDefinitions::getAllProviders()` order. Two providers delivering under one
 *   consumer key both run, and the LAST one wins, because it writes over what the earlier one left. A
 *   provider that throws stops the round, so provider order is also throw order.
 * - Consumer order is the order of `$children`. An indexed or sliced strategy hands out positions against
 *   the pool of matching children in exactly that sequence, so the caller flattening slots in a different
 *   order changes which child gets which item. The caller owns that ordering — the serving path passes
 *   children in slot-then-index order, which {@see ContextDeliveryResolver::childrenInDeliveryOrder()}
 *   produces.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContextDistributor
{
    public function __construct(
        private ContextPathResolver $pathResolver,
    ) {
    }

    /**
     * `$parentValues` is the parent's full working value map — its stored values, its loader-resolved values
     * and the context delivered to it, merged in {@see RenderedElementFactory}
     * precedence order. Deliberately NOT the union-filtered rendered map: an undeclared stored key is a
     * legitimate provider source, and filtering first would make it quietly stop delivering with no error
     * anywhere.
     *
     * A provider whose value is `null` distributes nothing to anyone and writes no key. That gate lives here
     * rather than in the strategies: {@see BroadcastDistributionConfig}
     * has no null check of its own and would hand the null to every consumer at once.
     *
     * @param array<string, mixed> $parentValues the parent's merged working values, keyed by property
     * @param list<StoredElement> $children the parent's direct children, in delivery order
     *
     * @throws ContentSystemException when a required consumer's path cannot be resolved
     *
     * @return list<ContextDelivery> one per child, positionally aligned with `$children`
     */
    public function distribute(StoredElement $parent, array $parentValues, array $children): array
    {
        $childCount = \count($children);
        $context = array_fill(0, $childCount, []);
        $referencedKeys = array_fill(0, $childCount, []);

        foreach ($parent->contextDefinitions->getAllProviders() as $contextKey => $provider) {
            $data = $parentValues[$contextKey] ?? null;

            if ($data === null) {
                continue;
            }

            $config = $provider->distributionConfig;
            $consumerKey = $config->getConsumerAlias() ?? $contextKey;
            $matched = $this->matchingChildren($children, $consumerKey);

            if ($matched === []) {
                continue;
            }

            $distributed = $config->distribute($data, $this->consumerData($children, $matched));

            foreach (array_values($matched) as $position => $childIndex) {
                if (!\array_key_exists($position, $distributed)) {
                    continue;
                }

                $context[$childIndex] = $this->deliverTo(
                    $children[$childIndex],
                    $consumerKey,
                    $distributed[$position],
                    $context[$childIndex]
                );

                if ($config instanceof KeyedDistributionConfig) {
                    $referencedKeys[$childIndex][] = $config->keyProperty;
                }
            }
        }

        $deliveries = [];
        foreach ($children as $index => $child) {
            $deliveries[] = new ContextDelivery(
                $child->id,
                $context[$index],
                array_values(array_unique($referencedKeys[$index]))
            );
        }

        return $deliveries;
    }

    /**
     * The indices of the children that consume this key, in `$children` order. Indices rather than elements,
     * because the position within this pool is what an indexed or sliced strategy hands items out against,
     * while the index within `$children` is where the result belongs.
     *
     * @param list<StoredElement> $children
     *
     * @return list<int>
     */
    private function matchingChildren(array $children, string $consumerKey): array
    {
        $matched = [];

        foreach ($children as $index => $child) {
            if ($this->acceptsContext($child, $consumerKey)) {
                $matched[] = $index;
            }
        }

        return $matched;
    }

    /**
     * A child accepts a provider key when any of its PARENT-scoped consumer keys matches it exactly or hangs
     * below it as a dot path. One child counts once however many of its keys match — it occupies a single
     * position in the distribution and then fills every matching key from that one value.
     *
     * A {@see ConsumerScope::Root} consumer is skipped here and in {@see deliverTo()}, the two places that
     * read the consumer map, so it is invisible to parent distribution end to end: it takes no position in an
     * indexed or sliced hand-out and receives no parent value. Its value comes from the root-ambient map
     * instead, which {@see ContextDeliveryResolver} overlays.
     */
    private function acceptsContext(StoredElement $child, string $consumerKey): bool
    {
        foreach ($child->contextDefinitions->getAllConsumers() as $declaredKey => $consumer) {
            if ($consumer->scope === ConsumerScope::Root) {
                continue;
            }

            if ($this->pathResolver->matches($consumerKey, $declaredKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The shape the strategies inspect. Property values are the child's STORED values, unwrapped — not its
     * working map. A keyed distribution's `keyProperty` therefore selects on an authored value, which is the
     * same value the distribution-referenced tier renders, so selection key and rendered key cannot diverge.
     * A `keyProperty` naming something only a loader produces selects nothing.
     *
     * @param list<StoredElement> $children
     * @param list<int> $matched
     *
     * @return list<array{component: string, properties: array<string, mixed>}>
     */
    private function consumerData(array $children, array $matched): array
    {
        $data = [];

        foreach ($matched as $childIndex) {
            $child = $children[$childIndex];

            $data[] = [
                'component' => $child->component,
                'properties' => array_map(
                    static fn (StoredValue $value): mixed => $value->jsonSerialize(),
                    $child->properties()
                ),
            ];
        }

        return $data;
    }

    /**
     * Writes one distributed value into every PARENT-scoped consumer key of this child that matches the
     * provider key. An exact match takes the value as it stands; a dot path resolves through it, which needs a
     * {@see Struct} to traverse — a required consumer that cannot get one fails naming this child, an
     * optional one takes an explicit null.
     *
     * The scope skip repeats {@see acceptsContext()}'s, because the two run against the same consumer map for
     * different questions and a root-scoped consumer must be absent from both answers.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function deliverTo(StoredElement $child, string $providerKey, mixed $data, array $context): array
    {
        foreach ($child->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if ($consumer->scope === ConsumerScope::Root) {
                continue;
            }

            if (!$this->pathResolver->matches($providerKey, $consumerKey)) {
                continue;
            }

            $context[$consumer->propertyAlias ?? $consumerKey] = $this->valueFor(
                $child,
                $consumerKey,
                $consumer,
                $providerKey,
                $data
            );
        }

        return $context;
    }

    private function valueFor(
        StoredElement $child,
        string $consumerKey,
        ContextConsumer $consumer,
        string $providerKey,
        mixed $data,
    ): mixed {
        if ($consumerKey === $providerKey) {
            return $data;
        }

        if (!$data instanceof Struct) {
            if ($consumer->required) {
                throw ContentSystemException::contextPathNotResolvable(
                    $consumerKey,
                    $child->id,
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
            $child->id
        );
    }
}
