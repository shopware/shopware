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

        $elements = $this->wrapVirtualRoot($elements, $specification);
        $this->resolvePlaceholders($elements, $specification);
        $this->expandRedistribution($elements);
        $elements = $this->pruneToTarget($elements, $specification);

        if ($mode === RenderingMode::FULL) {
            $hydratedElementsGenerator = $this->hydrationService->hydrate(
                $elements,
                $salesChannelContext,
                $specification->request,
                $cacheContext,
            );
            $elements = array_values(iterator_to_array($hydratedElementsGenerator, false));
        }

        $elements = $this->unwrapVirtualRoot($elements, $specification);
        $elements = $this->extractPartialTarget($elements, $specification);

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
     * Wraps layout roots with a temporary virtual root to distribute layout-level data as context.
     *
     * The virtual root is removed again by `unwrapVirtualRoot()`.
     *
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement>
     */
    private function wrapVirtualRoot(array $elements, RenderingSpecification $specification): array
    {
        if (!$this->virtualRootWrapper->requiresWrapping($specification, $elements)) {
            return $elements;
        }

        return [$this->virtualRootWrapper->wrap($elements, $specification)];
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
    private function pruneToTarget(array $elements, RenderingSpecification $specification): array
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return $elements;
        }

        return $this->partialRenderer->pruneToTarget($elements, $targetElementId);
    }

    /**
     * Removes the virtual root wrapper added by `wrapVirtualRoot()`, restoring the original layout structure.
     *
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement>
     */
    private function unwrapVirtualRoot(array $elements, RenderingSpecification $specification): array
    {
        if (!$this->virtualRootWrapper->requiresWrapping($specification, $elements)) {
            return $elements;
        }

        // VirtualRoot may be legitimately pruned during partial rendering when
        // target element doesn't need page-level context. Skip cleanup gracefully.
        if (!$this->virtualRootWrapper->isVirtualRoot($elements[0])) {
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
    private function extractPartialTarget(array $elements, RenderingSpecification $specification): array
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return $elements;
        }

        return [$this->partialRenderer->extractTarget($elements, $targetElementId)];
    }
}
