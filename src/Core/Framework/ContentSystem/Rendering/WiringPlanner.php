<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * The wiring step of the render path, moved out of ContentPipeline so the derivation and its
 * validation have one owner: it validates the stored forest's context wiring, expands `redistribute`
 * consumers into broadcast providers, and returns the tree the render step serves.
 *
 * The `*Planner` contract holds in full: `plan()` computes the redistribution-expanded stored tree
 * and returns it, and executes nothing. The plan IS the returned tree — not a description of work
 * left to do. `ContextDistributor` executes the actual distribution at delivery time, on that tree.
 *
 * Validation and derivation sit in one class although they judge different forests. The structural
 * wiring validation belongs to this component, and a defect in a subtree a partial render is about
 * to discard must still fail the render — so validation runs on the pre-prune forest while
 * derivation runs on the pruned tree the render actually serves, and the class takes both forests.
 *
 * The planner is mode-blind: the derivation runs identically in FULL and SKELETON, throws nothing
 * (every throw lives in validation), and its result is inert in SKELETON mode, where nothing
 * distributes. Gating the derivation to FULL-only is deliberately not done here; it becomes
 * mandatory only if the derivation ever gains a throw or an observable side effect.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WiringPlanner
{
    public function __construct(
        private ProviderDeliveryKeyResolver $providerDeliveryKeys,
    ) {
    }

    /**
     * Runs the structural wiring validation over `$prePruneForest` (a defect in a subtree the partial
     * prune discarded still fails the render), then derives the redistribution expansion over
     * `$prunedTree` and returns the derived tree. Validation throws; derivation never throws.
     *
     * @param list<StoredElement> $prePruneForest
     * @param list<StoredElement> $prunedTree
     *
     * @throws ContentSystemException
     *
     * @return list<StoredElement>
     */
    public function plan(array $prePruneForest, array $prunedTree): array
    {
        $this->validateWiring($prePruneForest);

        return $this->deriveRedistribution($prunedTree);
    }

    /**
     * Rejects a context-wiring defect anywhere in the forest.
     *
     * It runs on the pre-prune forest, so a defect inside a subtree a partial render is about to
     * discard still fails the request: whether an element is served decides nothing about whether
     * its wiring is valid.
     *
     * Validation is deliberately separate from {@see deriveRedistribution()} — a derivation that also
     * throws would carry these rejections onto whatever tree it happens to run on, and today that is
     * the pruned one.
     *
     * @param list<StoredElement> $elements
     */
    private function validateWiring(array $elements): void
    {
        foreach ($elements as $element) {
            $consumers = $element->contextDefinitions->getAllConsumers();

            $this->validatePropertyAliases($consumers);
            $this->validateRedistribution($consumers, $element->contextDefinitions->getAllProviders());
            $this->providerDeliveryKeys->resolve($element->contextDefinitions, $element->id);

            foreach ($element->slots as $children) {
                $this->validateWiring($children);
            }
        }
    }

    /**
     * Validates property alias uniqueness within an element.
     *
     * @param array<string, ContextConsumer> $consumers
     */
    private function validatePropertyAliases(array $consumers): void
    {
        $propertyKeys = [];

        foreach ($consumers as $contextKey => $consumer) {
            $propertyKey = $consumer->propertyAlias ?? $contextKey;

            $baseKey = str_contains($propertyKey, '.')
                ? substr($propertyKey, 0, (int) strpos($propertyKey, '.'))
                : $propertyKey;

            if (\array_key_exists($baseKey, $propertyKeys)) {
                throw ContentSystemException::propertyAliasCollision(
                    $baseKey,
                    $propertyKeys[$baseKey],
                    $contextKey
                );
            }

            $propertyKeys[$baseKey] = $contextKey;
        }
    }

    /**
     * Rejects a redistributing consumer the derivation could not turn into a provider: one keyed by a
     * dotted path, and one whose derived provider key an authored provider already holds.
     *
     * The derived key is the property the consumer writes ({@see generateVirtualProviders()}), so that is
     * what the collision is judged on — a `consumerAlias` renames what children match, not where the value
     * is read from, and can therefore never collide with an authored provider key.
     *
     * @param array<string, ContextConsumer> $consumers
     * @param array<string, ContextProvider> $existingProviders
     */
    private function validateRedistribution(array $consumers, array $existingProviders): void
    {
        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            if (\array_key_exists($consumer->propertyAlias ?? $contextKey, $existingProviders)) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }
        }
    }

    /**
     * Pure derivation: {@see validateWiring()} has already rejected every consumer this could not
     * express, so nothing here throws.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement>
     */
    private function deriveRedistribution(array $elements): array
    {
        return array_map($this->deriveRedistributionRecursively(...), $elements);
    }

    /**
     * Rebuilds the element rather than mutating it: the children are expanded first and the node is
     * rebuilt only where something actually changed.
     */
    private function deriveRedistributionRecursively(StoredElement $element): StoredElement
    {
        $virtualProviders = $this->generateVirtualProviders(
            $element->contextDefinitions->getAllConsumers(),
            $element->contextDefinitions->getAllProviders()
        );

        $slots = [];
        $slotsChanged = false;

        foreach ($element->slots as $slotName => $children) {
            $expandedChildren = [];

            foreach ($children as $child) {
                $expandedChild = $this->deriveRedistributionRecursively($child);
                $slotsChanged = $slotsChanged || $expandedChild !== $child;
                $expandedChildren[] = $expandedChild;
            }

            $slots[$slotName] = $expandedChildren;
        }

        if ($slotsChanged) {
            $element = $element->withSlots($slots);
        }

        if ($virtualProviders === []) {
            return $element;
        }

        return $element->withContextDefinitions($element->contextDefinitions->withAddedProviders($virtualProviders));
    }

    /**
     * Generates virtual providers from consumers with redistribute flag.
     *
     * The provider is keyed by the property the consumer actually writes its received value to
     * (`propertyAlias ?? contextKey`), because a provider's key is the property
     * {@see ContextDistributor} reads the
     * value from. The name children receive it under is a separate concern, carried by the broadcast
     * config's `consumerAlias` — the same selection mechanism an authored provider uses. Keying the
     * provider by `consumerAlias` instead would name a property the element never wrote, so a chained
     * redistribution would silently deliver nothing.
     *
     * The alias is set only where the two keys genuinely differ. Where they coincide the config stays
     * plain, because the config is serialized verbatim into a full-format response and an alias that
     * merely restates the provider key would change that wire shape for no behavioural gain.
     *
     * A consumer whose derived key an authored provider already holds is skipped rather than merged:
     * the validation pass has already rejected that tree, so this branch only keeps the derivation
     * from silently overwriting an authored provider if it is ever run on an unvalidated forest.
     *
     * @param array<string, ContextConsumer> $consumers
     * @param array<string, ContextProvider> $existingProviders
     *
     * @return array<string, ContextProvider>
     */
    private function generateVirtualProviders(array $consumers, array $existingProviders): array
    {
        $virtualProviders = [];

        foreach ($consumers as $contextKey => $consumer) {
            if (!$consumer->redistribute) {
                continue;
            }

            $providerKey = $consumer->propertyAlias ?? $contextKey;
            $childKey = $this->providerDeliveryKeys->derivedChildKey($consumer, $contextKey);

            if (\array_key_exists($providerKey, $existingProviders)) {
                continue;
            }

            $virtualProviders[$providerKey] = new ContextProvider(
                $consumer->type,
                $childKey === $providerKey
                    ? BroadcastDistributionConfig::simple()
                    : BroadcastDistributionConfig::aliased($childKey)
            );
        }

        return $virtualProviders;
    }
}
