<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Computes the context available at an element's position with one formula for every depth: the ancestor-chain
 * exposure plus the root-ambient set appended verbatim. Each ancestor exposes to its direct children only the
 * declared providers that resolve on it (Level 2) plus the redistribute consumers whose key actually flows into
 * it along the CHAIN. This mirrors runtime delivery (ContentPipeline's redistribute-derivation step turning
 * redistribute flags into broadcast providers + ContextDistributor delivering to direct children only), so the
 * gate honors the rule that element-provided context travels past direct children solely via explicit
 * redistribution, while root-ambient context reaches every depth directly and never rides a chain.
 * Section-agnostic: the root-ambient set is passed in (entity assignment yields the page entity; header/footer
 * yield nothing).
 *
 * Public Core service so the diagnostics kernel and the future mutation operations share one context walk.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AvailableContextResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly ElementResolver $elementResolver,
        private readonly ProviderDeliveryKeyResolver $providerDeliveryKeys,
    ) {
    }

    /**
     * @param list<StoredElement> $tree the layout's root elements
     * @param list<ProvidedContext> $rootContext the layout's root-ambient context (broadcast Single), available
     *                                           at every depth
     *
     * @throws ContentSystemException when an element on the target's path carries two child-facing key
     *                                producers — authored providers, redistribute consumers, or one of
     *                                each — that deliver to children under the same key
     *
     * @return list<ProvidedContext>
     */
    public function resolve(string $targetElementId, array $tree, array $rootContext): array
    {
        $location = $this->locate($tree, $targetElementId);

        if ($location === null) {
            return [];
        }

        ['ancestors' => $ancestors, 'target' => $target] = $location;

        // The target's own provider set is judged too: a top-level element has no ancestors, so skipping it
        // would leave its providers unchecked.
        $this->providerDeliveryKeys->resolve($target->contextDefinitions, $target->id);

        $incoming = [];

        foreach ($ancestors as $ancestor) {
            $this->providerDeliveryKeys->resolve($ancestor->contextDefinitions, $ancestor->id);
            $incoming = $this->expose($ancestor, $incoming, $rootContext);
        }

        return [...$incoming, ...$rootContext];
    }

    /**
     * The context an element delivers to its direct children: its declared providers that resolve on it, plus
     * the keys it re-broadcasts via redistribute consumers whose key is present in its own CHAIN incoming set.
     * Both mint sites leave {@see ProvidedContext::$root} at its `false` default: an ancestor's exposure is
     * element-provided even when the value it relays originated at the root.
     *
     * @param list<ProvidedContext> $incoming context this element received off the ancestor chain
     * @param list<ProvidedContext> $ambient the layout's root-ambient context, reachable by this element's own
     *                                       root-scoped consumers and therefore able to back its providers
     *
     * @return list<ProvidedContext>
     */
    private function expose(StoredElement $element, array $incoming, array $ambient): array
    {
        $exposed = [];

        foreach ($element->contextDefinitions->getAllProviders() as $contextKey => $provider) {
            $fqcn = $this->resolveProvidedFqcn($element->component, (string) $contextKey);

            if ($fqcn === null) {
                continue;
            }

            if (!$this->providerResolves($element, (string) $contextKey, [...$incoming, ...$ambient])) {
                continue;
            }

            $exposed[] = new ProvidedContext(
                contextKey: $provider->distributionConfig->getConsumerAlias() ?? (string) $contextKey,
                fqcn: $fqcn,
                contextType: $provider->type,
                providerElementId: $element->id,
                distribution: $provider->distributionConfig->getStrategy(),
            );
        }

        foreach ($element->contextDefinitions->getAllConsumers() as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            $match = $this->firstWithKey($incoming, (string) $contextKey);

            if ($match === null) {
                continue;
            }

            $exposed[] = new ProvidedContext(
                contextKey: $consumer->consumerAlias ?? (string) $contextKey,
                fqcn: $match->fqcn,
                contextType: $consumer->type,
                providerElementId: $element->id,
                distribution: DistributionStrategy::Broadcast,
            );
        }

        return $exposed;
    }

    /**
     * Level-2 backing: a declared provider delivers only when its own property resolves at its position.
     * Reuses {@see ElementResolver} (the single source of truth), so the parent (received-context), root,
     * loader, and ambiguity rules that decide a consumed property's verdict also decide a provider's backing.
     * The available set it judges against is the chain incoming plus the root-ambient set, so a provider
     * property filled through the element's own root-scoped consumer backs the provider, and what that
     * provider then hands downstream is element-provided.
     *
     * @param list<ProvidedContext> $available
     */
    private function providerResolves(StoredElement $element, string $key, array $available): bool
    {
        $resolutions = $this->elementResolver->resolve($element, new ResolutionContext($element->id, $available));

        foreach ($resolutions as $resolution) {
            if ($resolution->key !== $key) {
                continue;
            }

            return $resolution->resolved !== null;
        }

        return false;
    }

    /**
     * @param list<ProvidedContext> $incoming the CHAIN incoming set only: a redistribute consumer relays what it
     *                                        received off its parent, never the root-ambient set
     */
    private function firstWithKey(array $incoming, string $key): ?ProvidedContext
    {
        foreach ($incoming as $provided) {
            if ($provided->contextKey === $key) {
                return $provided;
            }
        }

        return null;
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return array{ancestors: list<StoredElement>, target: StoredElement}|null the ancestor path (root..parent) and the target element, or null if not found
     */
    private function locate(array $tree, string $targetElementId): ?array
    {
        foreach ($tree as $root) {
            if ($root->id === $targetElementId) {
                return ['ancestors' => [], 'target' => $root];
            }

            $found = $this->search($root, [$root], $targetElementId);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param list<StoredElement> $path elements from the root down to and including $element
     *
     * @return array{ancestors: list<StoredElement>, target: StoredElement}|null the ancestor path and the target, or null if the target is not below $element
     */
    private function search(StoredElement $element, array $path, string $targetElementId): ?array
    {
        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                if ($child->id === $targetElementId) {
                    return ['ancestors' => $path, 'target' => $child];
                }

                $deeper = $this->search($child, [...$path, $child], $targetElementId);

                if ($deeper !== null) {
                    return $deeper;
                }
            }
        }

        return null;
    }

    private function resolveProvidedFqcn(string $component, string $contextKey): ?string
    {
        if (!$this->registry->has($component)) {
            return null;
        }

        $properties = $this->registry->get($component)->properties();

        if (!isset($properties[$contextKey])) {
            return null;
        }

        $type = $properties[$contextKey]->type();
        $declaredType = $type->type();

        if ($type->isPrimitive() || !\is_string($declaredType) || $declaredType === 'object') {
            return null;
        }

        return $declaredType;
    }
}
