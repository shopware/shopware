<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
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
        private readonly WiringPlanner $wiringPlanner,
        private readonly ElementLowering $elementLowering,
        private readonly ContentElementLowering $bridge,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
    ) {
    }

    /**
     * @param bool $collectValueIndex whether the response format rebuilds its body from the
     *                                {@see \Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex}
     *                                instead of serving property values inline. The flag has no consumer yet:
     *                                the lowering records no value provenance, so there is nothing to index
     *                                from and every format gets a null index. The commit that puts the
     *                                decomposed and data formats on their own encoders adds the collection
     *                                behind this flag and is its first reader.
     */
    public function load(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        bool $collectValueIndex,
        SalesChannelContext $salesChannelContext,
    ): RenderResult {
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

        $storedTree = $this->wiringPlanner->plan($preparation->prePruneForest, $preparation->tree);

        $renderedTree = $this->elementLowering->lower(
            $storedTree,
            $mode,
            $salesChannelContext,
            $specification->request,
            $cacheContext,
        );

        $renderedTree = $this->unwrapVirtualRoot($renderedTree, $scaffolding);
        $renderedTree = $this->extractPartialTarget($renderedTree, $scaffolding);

        $finalizationEvent = new RenderedTreeFinalizationEvent(
            $renderedTree,
            $layout->reference,
            $specification,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($finalizationEvent);

        // The bridge lowers the tree the event handed back, so a listener's replacement reaches the response.
        $finishedTree = $finalizationEvent->tree();
        $elements = $this->bridge->lowerTree($storedTree, $finishedTree);

        $reference = $finalizationEvent->layout;

        return new RenderResult(
            $finishedTree,
            $reference,
            null,
            new ContentPage(
                $reference->id,
                $elements,
                $reference->name,
                $reference->version,
            ),
        );
    }

    /**
     * Removes the virtual root wrapper, restoring the original layout structure.
     *
     * @param list<RenderedElement> $elements
     *
     * @return list<RenderedElement>
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
     * @param list<RenderedElement> $elements
     *
     * @return list<RenderedElement>
     */
    private function extractPartialTarget(array $elements, RenderScaffolding $scaffolding): array
    {
        if ($scaffolding->extractTargetId === null) {
            return $elements;
        }

        return [$this->partialRenderer->extractTarget($elements, $scaffolding->extractTargetId)];
    }
}
