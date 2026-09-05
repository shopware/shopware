<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class PageContextConsumerWiring
{
    /**
     * @param array<string, list<PropertyResolution>> $resolutions per-element resolutions, keyed by element id
     * @param list<ProvidedContext> $rootContext the bound source's root-ambient context
     */
    public function apply(StoredTree $tree, array $resolutions, array $rootContext): StoredTree
    {
        $roots = [];
        $changed = false;

        foreach ($tree->roots as $root) {
            [$rewired] = $this->wire($root, $resolutions, $rootContext);
            $changed = $changed || $rewired !== $root;
            $roots[] = $rewired;
        }

        // A mutation without a page-context consumer to wire leaves the tree byte-for-byte identical, so the
        // original instance is returned unchanged rather than an equal rebuild.
        return $changed ? new StoredTree($roots) : $tree;
    }

    /**
     * StoredElement is immutable, so the tree is rebuilt bottom-up: each element returns its rewired copy plus
     * the context keys its subtree consumes (with their types), which every ancestor must redistribute.
     *
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param list<ProvidedContext> $rootContext
     *
     * @return array{0: StoredElement, 1: array<string, ContextType>}
     */
    private function wire(StoredElement $element, array $resolutions, array $rootContext): array
    {
        $descendantRequirements = [];
        $slots = [];
        $changed = false;

        foreach ($element->slots as $slotName => $children) {
            $rewired = [];
            foreach ($children as $child) {
                [$rewiredChild, $childRequirements] = $this->wire($child, $resolutions, $rootContext);
                $changed = $changed || $rewiredChild !== $child;
                $rewired[] = $rewiredChild;

                foreach ($childRequirements as $key => $type) {
                    $descendantRequirements[$key] ??= $type;
                }
            }
            $slots[$slotName] = $rewired;
        }

        $definitions = $element->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $consumerCount = \count($consumers);
        $requirements = $descendantRequirements;

        $consumed = $this->findConsumed($resolutions[$element->id] ?? [], $rootContext);

        if ($consumed !== null) {
            [$key, $type, $required] = $consumed;
            $requirements[$key] ??= $type;

            // Never override an existing consumer (authored, or already wired for another descendant).
            if (!isset($consumers[$key])) {
                $consumers[$key] = new ContextConsumer($type, $required);
            }
        }

        // Every context key a descendant consumes is redistributed by this element, unless it already owns a
        // consumer for that key.
        foreach ($descendantRequirements as $key => $type) {
            if (!isset($consumers[$key])) {
                $consumers[$key] = new ContextConsumer($type, false, true);
            }
        }

        if (!$changed && \count($consumers) === $consumerCount) {
            return [$element, $requirements];
        }

        $element = $element
            ->withSlots($slots)
            ->withContextDefinitions(new ContextDefinitions($definitions->getAllProviders(), $consumers));

        return [$element, $requirements];
    }

    /**
     * @param list<PropertyResolution> $resolutions
     * @param list<ProvidedContext> $rootContext
     *
     * @return array{0: string, 1: ContextType, 2: bool}|null the context key, its type and whether it is required
     */
    private function findConsumed(array $resolutions, array $rootContext): ?array
    {
        foreach ($resolutions as $resolution) {
            if ($resolution->kind !== PropertyKind::Reference) {
                continue;
            }

            $resolved = $resolution->resolved;

            if ($resolved !== null
                && $resolved->origin === CandidateOrigin::Parent
                && $resolved->contextKey === $resolution->key
                && $resolved->contextType !== null
            ) {
                return [$resolved->contextKey, $resolved->contextType, $resolution->required];
            }

            if ($resolved === null) {
                foreach ($rootContext as $context) {
                    if ($context->contextKey === $resolution->key && $context->fqcn === $resolution->fqcn) {
                        return [$context->contextKey, $context->contextType, $resolution->required];
                    }
                }
            }
        }

        return null;
    }
}
