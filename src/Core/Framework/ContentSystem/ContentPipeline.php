<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Hydration\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentPipeline
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StoredTreePreparer $storedTreePreparer,
        private readonly ElementLowering $elementLowering,
        private readonly ContentElementLowering $bridge,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
    ) {
    }

    public function load(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        SalesChannelContext $salesChannelContext,
    ): ContentPage {
        $preparationEvent = new ContentTreePreparationEvent(
            $layout->elements,
            $layout->reference,
            $specification,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($preparationEvent);

        $preparation = $this->storedTreePreparer->prepare($preparationEvent->tree(), $specification, $mode);
        $scaffolding = $preparation->scaffolding;

        $this->validateWiring($preparation->prePruneForest);

        $storedTree = $this->deriveRedistribution($preparation->tree);

        $renderedTree = $this->elementLowering->lower(
            $storedTree,
            $mode,
            $salesChannelContext,
            $specification->request,
            $cacheContext,
        );

        $elements = $this->bridge->lowerTree($storedTree, $renderedTree);

        $elements = $this->unwrapVirtualRoot($elements, $scaffolding);
        $elements = $this->extractPartialTarget($elements, $scaffolding);

        $afterHydrationEvent = new PostHydrationEvent(
            $elements,
            $layout->reference,
            $specification,
            $mode,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($afterHydrationEvent);

        $reference = $afterHydrationEvent->layout;

        return new ContentPage(
            $reference->id,
            $afterHydrationEvent->elements,
            $reference->name,
            $reference->version,
        );
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
     * Expands redistribute flags on consumers into broadcast providers.
     *
     * Consumers with `redistribute: true` automatically provide their received context
     * to descendants. This step generates the ContextProvider objects that enable
     * this behavior during rendering.
     *
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
     * {@see \Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextResolutionVisitor} reads the
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
            $childKey = $consumer->consumerAlias ?? $contextKey;

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

    /**
     * Removes the virtual root wrapper, restoring the original layout structure.
     *
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement>
     */
    private function unwrapVirtualRoot(array $elements, RenderScaffolding $scaffolding): array
    {
        if (!$scaffolding->virtualRootSurvivedPrune) {
            return $elements;
        }

        return $this->virtualRootWrapper->unwrap($elements[0]);
    }

    /**
     * Extracts the target element and its descendants for partial rendering.
     *
     * Removes the parent elements that `pruneToTarget()` kept for context distribution.
     *
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement>
     */
    private function extractPartialTarget(array $elements, RenderScaffolding $scaffolding): array
    {
        if ($scaffolding->extractTargetId === null) {
            return $elements;
        }

        return [$this->partialRenderer->extractTarget($elements, $scaffolding->extractTargetId)];
    }
}
