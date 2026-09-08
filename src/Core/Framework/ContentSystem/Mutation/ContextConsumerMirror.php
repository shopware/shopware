<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementWiringDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerBaseKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * Mirrors resolved acceptsContext consumers onto the elements a mutation created: a parent-resolved reference
 * becomes a parent-scope consumer, a root-resolved one a {@see ConsumerScope::Root} consumer.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContextConsumerMirror
{
    /**
     * @param array<string, list<PropertyResolution>> $resolutions per-element resolutions, keyed by element id
     * @param list<string> $createdElementIds the mutation's created() ids
     */
    public function apply(StoredTree $tree, array $resolutions, array $createdElementIds): StoredTree
    {
        if ($createdElementIds === []) {
            return $tree;
        }

        $created = array_flip($createdElementIds);
        $roots = [];
        $changed = false;

        foreach ($tree->roots as $root) {
            $rewired = $this->wire($root, $resolutions, $created);
            $changed = $changed || $rewired !== $root;
            $roots[] = $rewired;
        }

        // No consumer written, so this identical instance is returned.
        return $changed ? new StoredTree($roots) : $tree;
    }

    /**
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param array<string, int> $created
     */
    private function wire(StoredElement $element, array $resolutions, array $created): StoredElement
    {
        $slots = [];
        $slotsChanged = false;

        foreach ($element->slots as $slotName => $children) {
            $rewired = [];

            foreach ($children as $child) {
                $rewiredChild = $this->wire($child, $resolutions, $created);
                $slotsChanged = $slotsChanged || $rewiredChild !== $child;
                $rewired[] = $rewiredChild;
            }

            $slots[$slotName] = $rewired;
        }

        $consumers = $this->mirroredConsumers($element, $resolutions, $created);

        if ($consumers !== null) {
            $element = $element->withContextDefinitions(
                new ContextDefinitions($element->contextDefinitions->getAllProviders(), $consumers)
            );
        }

        if ($slotsChanged) {
            $element = $element->withSlots($slots);
        }

        return $element;
    }

    /**
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param array<string, int> $created
     *
     * @return array<string, ContextConsumer>|null the element's full consumer map, or null when it gains none
     */
    private function mirroredConsumers(StoredElement $element, array $resolutions, array $created): ?array
    {
        if (!isset($created[$element->id])) {
            return null;
        }

        $definitions = $element->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $providers = $definitions->getAllProviders();
        $added = false;
        $consumerBaseKey = new ConsumerBaseKeyResolver();

        foreach ($resolutions[$element->id] ?? [] as $resolution) {
            $mirrored = $this->consumerFor($resolution);

            if ($mirrored === null) {
                continue;
            }

            [$contextKey, $consumer] = $mirrored;
            $writtenKey = $resolution->key;

            if (isset($consumers[$contextKey]) || $this->collidesOnBaseKey($consumerBaseKey, $writtenKey, $consumers)) {
                continue;
            }

            // Skip a loader-filled written key, or one the element itself provides (mirroring would make it a conduit).
            if (isset($element->dataRequirements[$writtenKey]) || isset($providers[$writtenKey])) {
                continue;
            }

            $consumers[$contextKey] = $consumer;
            $added = true;
        }

        return $added ? $consumers : null;
    }

    /**
     * The collision axis {@see StoredElementWiringDecoder::rejectInvalidElementWiring()} enforces at decode: reject
     * it here too, or mirroring would write a consumer pair the next decode throws on.
     *
     * @param array<string, ContextConsumer> $consumers
     */
    private function collidesOnBaseKey(ConsumerBaseKeyResolver $consumerBaseKey, string $writtenKey, array $consumers): bool
    {
        $baseKey = $consumerBaseKey->resolve($writtenKey);

        foreach ($consumers as $consumerKey => $consumer) {
            if ($consumerBaseKey->resolve($consumer->propertyAlias ?? $consumerKey) === $baseKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: ContextConsumer}|null the context key and the consumer to write under it
     */
    private function consumerFor(PropertyResolution $resolution): ?array
    {
        if ($resolution->kind !== PropertyKind::Reference) {
            return null;
        }

        $resolved = $resolution->resolved;

        if ($resolved === null || $resolved->contextType === null) {
            return null;
        }

        $key = $resolved->contextKey;

        if ($key === null || $key === '') {
            return null;
        }

        // The written property must be the reference property whose resolution proved the consumer, or delivery
        // fills a foreign key while the declared property stays empty.
        $propertyAlias = $resolution->key !== $key ? $resolution->key : null;

        // A dotted propertyAlias is rejected at decode time ({@see StoredElementWiringDecoder}), so mirroring must not write one.
        if ($propertyAlias !== null && str_contains($propertyAlias, '.')) {
            return null;
        }

        if ($resolved->origin === CandidateOrigin::Parent) {
            return [$key, new ContextConsumer($resolved->contextType, $resolution->required, propertyAlias: $propertyAlias)];
        }

        if ($resolved->origin === CandidateOrigin::Root) {
            return [$key, new ContextConsumer($resolved->contextType, $resolution->required, propertyAlias: $propertyAlias, scope: ConsumerScope::Root)];
        }

        return null;
    }
}
