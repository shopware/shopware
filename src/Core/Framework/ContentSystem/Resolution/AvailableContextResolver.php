<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Computes the context available at an element's position by simulating the redistribution chain top-down
 * along the located ancestor path. Each ancestor exposes to its direct children only the declared providers
 * that resolve on it (Level 2) plus the redistribute consumers whose key actually flows into it. This mirrors
 * runtime delivery (RedistributeExpansionSubscriber expanding redistribute flags into broadcast providers +
 * ContextResolutionVisitor distributing to direct children only), so the gate honors the rule that context
 * travels past direct children solely via explicit redistribution. A top-level element sees the bound source's
 * root-ambient context. Section-agnostic: the root-ambient set is passed in (entity assignment yields the page
 * entity; header/footer yield nothing).
 *
 * Public Core service so the diagnostics kernel and the future mutation operations share one context walk.
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
    ) {
    }

    /**
     * @param list<ContentElement> $tree the layout's root elements
     * @param list<ProvidedContext> $rootContext root-ambient context for top-level elements (broadcast Single)
     *
     * @return list<ProvidedContext>
     */
    public function resolve(string $targetElementId, array $tree, array $rootContext): array
    {
        $location = $this->locate($tree, $targetElementId);

        if ($location === null) {
            return [];
        }

        ['ancestors' => $ancestors, 'topLevel' => $topLevel] = $location;

        if ($topLevel) {
            return $rootContext;
        }

        $incoming = $rootContext;

        foreach ($ancestors as $ancestor) {
            $incoming = $this->expose($ancestor, $incoming);
        }

        return $incoming;
    }

    /**
     * The context an element delivers to its direct children: its declared providers that resolve on it, plus
     * the keys it re-broadcasts via redistribute consumers whose key is present in its own incoming set.
     *
     * @param list<ProvidedContext> $incoming context available to this element from its parent
     *
     * @return list<ProvidedContext>
     */
    private function expose(ContentElement $element, array $incoming): array
    {
        $exposed = [];

        foreach ($element->getProvidesContext() as $contextKey => $provider) {
            $fqcn = $this->resolveProvidedFqcn($element->getComponent(), (string) $contextKey);

            if ($fqcn === null) {
                continue;
            }

            if (!$this->providerResolves($element, (string) $contextKey, $incoming)) {
                continue;
            }

            $exposed[] = new ProvidedContext(
                contextKey: (string) $contextKey,
                fqcn: $fqcn,
                contextType: $provider->type,
                providerElementId: $element->getId(),
                distribution: $provider->distributionConfig->getStrategy(),
            );
        }

        foreach ($element->getAcceptsContext() as $contextKey => $consumer) {
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
                providerElementId: $element->getId(),
                distribution: DistributionStrategy::Broadcast,
            );
        }

        return $exposed;
    }

    /**
     * Level-2 backing: a declared provider delivers only when its own property resolves at its position.
     * Reuses {@see ElementResolver} (the single source of truth), so the parent (received-context), loader,
     * and ambiguity rules that decide a consumed property's verdict also decide a provider's backing.
     *
     * @param list<ProvidedContext> $incoming
     */
    private function providerResolves(ContentElement $element, string $key, array $incoming): bool
    {
        $resolutions = $this->elementResolver->resolve($element, new ResolutionContext($element->getId(), $incoming));

        foreach ($resolutions as $resolution) {
            if ($resolution->key !== $key) {
                continue;
            }

            return $resolution->resolved !== null;
        }

        return false;
    }

    /**
     * @param list<ProvidedContext> $incoming
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
     * @param list<ContentElement> $tree
     *
     * @return array{ancestors: list<ContentElement>, topLevel: bool}|null the ancestor path (root..parent), or null if not found
     */
    private function locate(array $tree, string $targetElementId): ?array
    {
        foreach ($tree as $root) {
            if ($root->getId() === $targetElementId) {
                return ['ancestors' => [], 'topLevel' => true];
            }

            $ancestors = $this->search($root, [$root], $targetElementId);

            if ($ancestors !== null) {
                return ['ancestors' => $ancestors, 'topLevel' => false];
            }
        }

        return null;
    }

    /**
     * @param list<ContentElement> $path elements from the root down to and including $element
     *
     * @return list<ContentElement>|null the ancestor path to the target, or null if the target is not below $element
     */
    private function search(ContentElement $element, array $path, string $targetElementId): ?array
    {
        foreach ($element->allSlotElements() as $child) {
            if ($child->getId() === $targetElementId) {
                return $path;
            }

            $deeper = $this->search($child, [...$path, $child], $targetElementId);

            if ($deeper !== null) {
                return $deeper;
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

        if ($type->isPrimitive()) {
            return null;
        }

        return $type->type();
    }
}
