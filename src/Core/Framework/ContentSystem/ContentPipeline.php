<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Event\PreContentHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
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
        private readonly ContentElementHydrator $hydrationService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer
    ) {
    }

    public function load(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        SalesChannelContext $salesChannelContext,
    ): ContentPage {
        $preHydrationEvent = new PreContentHydrationEvent(
            $layout->elements,
            $layout->reference,
            $specification,
            $mode,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($preHydrationEvent);
        $elements = $preHydrationEvent->elements;

        $virtualRootWrapped = $this->virtualRootWrapper->requiresWrapping($specification, $elements);

        if ($virtualRootWrapped) {
            $elements = [$this->virtualRootWrapper->wrap($elements, $specification)];
        }

        $this->resolvePlaceholders($elements, $specification);
        $this->expandRedistribution($elements);

        $extractTargetId = $this->extractTargetId($specification);
        $elements = $this->pruneToTarget($elements, $extractTargetId);
        $scaffolding = $this->deriveScaffolding($elements, $extractTargetId, $virtualRootWrapped);

        if ($mode === RenderingMode::FULL) {
            $hydratedElementsGenerator = $this->hydrationService->hydrate(
                $elements,
                $salesChannelContext,
                $specification->request,
                $cacheContext,
            );
            $elements = array_values(iterator_to_array($hydratedElementsGenerator, false));
        }

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
     * Records what the finishing steps need to know about the tree the preparation steps produced.
     *
     * `virtualRootSurvivedPrune` is read off the post-prune forest, because the wrap decision alone
     * does not answer whether the virtual root is still there: a partial render addressed at an
     * element that needs no page-level context prunes it away. Element ids are unique across roots,
     * so pruning leaves at most one surviving root whenever a target is set and the first root
     * decides. `$extractTargetId` is passed in already normalised — the prune ran on it.
     *
     * @param list<ContentElement> $elements
     */
    private function deriveScaffolding(array $elements, ?string $extractTargetId, bool $virtualRootWrapped): RenderScaffolding
    {
        $virtualRootSurvivedPrune = $virtualRootWrapped
            && $elements !== []
            && $this->virtualRootWrapper->isVirtualRoot($elements[0]);

        return new RenderScaffolding($virtualRootSurvivedPrune, $extractTargetId);
    }

    /**
     * The id a partial render extracts, or null when the request addresses the whole layout.
     */
    private function extractTargetId(RenderingSpecification $specification): ?string
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return null;
        }

        return $targetElementId;
    }

    /**
     * Resolves `{{variable}}` placeholders in element properties.
     *
     * Single-pass only, no recursive resolution. Placeholders are replaced
     * with values from the RenderingSpecification before hydration starts.
     *
     * @param list<ContentElement> $elements
     */
    private function resolvePlaceholders(array $elements, RenderingSpecification $specification): void
    {
        // ContentElement is mutable, so changes happen in place
        foreach ($elements as $element) {
            $element->replacePlaceholders($specification);
        }
    }

    /**
     * Expands redistribute flags on consumers into broadcast providers.
     *
     * Consumers with `redistribute: true` automatically provide their received context
     * to descendants. This step generates the ContextProvider objects that enable
     * this behavior during rendering.
     *
     * @param list<ContentElement> $elements
     */
    private function expandRedistribution(array $elements): void
    {
        foreach ($elements as $element) {
            $this->expandRedistributionRecursively($element);
        }
    }

    private function expandRedistributionRecursively(ContentElement $element): void
    {
        $consumers = $element->getAcceptsContext();
        $providers = $element->getProvidesContext();

        $this->validatePropertyAliases($consumers);
        $virtualProviders = $this->generateVirtualProviders($consumers, $providers);

        if ($virtualProviders !== []) {
            $newDefinitions = $element->getContextDefinitions()->withAddedProviders($virtualProviders);
            $element->setContextDefinitions($newDefinitions);
        }

        foreach ($element->allSlotElements() as $child) {
            $this->expandRedistributionRecursively($child);
        }
    }

    /**
     * Generates virtual providers from consumers with redistribute flag.
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

            if (str_contains($contextKey, '.')) {
                throw ContentSystemException::redistributeWithDottedPath($contextKey);
            }

            $providerKey = $consumer->consumerAlias ?? $contextKey;

            if (\array_key_exists($providerKey, $existingProviders)) {
                throw ContentSystemException::redistributeConflict($contextKey);
            }

            $virtualProviders[$providerKey] = new ContextProvider(
                $consumer->type,
                BroadcastDistributionConfig::simple()
            );
        }

        return $virtualProviders;
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
     * Prunes the layout tree to the target element and its dependencies when the `elementId` parameter is present.
     *
     * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow;
     * `extractPartialTarget()` removes those ancestors after hydration.
     *
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement>
     */
    private function pruneToTarget(array $elements, ?string $extractTargetId): array
    {
        if ($extractTargetId === null) {
            return $elements;
        }

        return $this->partialRenderer->pruneToTarget($elements, $extractTargetId);
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
