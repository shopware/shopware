<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
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
        private readonly ResolvedValueIndexFactory $indexFactory,
    ) {
    }

    /**
     * @param bool $collectValueIndex whether the response format rebuilds its body from the
     *                                {@see ResolvedValueIndex} instead of serving property values inline. The
     *                                index is finalized against the FINISHED tree — after the finishing steps
     *                                and the finalization event — so a node a partial extract dropped and a
     *                                key a listener rewrote are both accounted for as the response will carry
     *                                them, not as the lowering produced them.
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

        $lowered = $this->elementLowering->lower(
            $storedTree,
            $mode,
            $salesChannelContext,
            $specification->request,
            $cacheContext,
        );

        $renderedTree = $this->unwrapVirtualRoot($lowered->tree, $scaffolding);
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
            $collectValueIndex ? $this->indexFactory->create($finishedTree, $lowered->provenance) : null,
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
