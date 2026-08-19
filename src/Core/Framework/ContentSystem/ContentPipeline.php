<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
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

        $storedTree = $this->wiringPlanner->plan($preparation->prePruneForest, $preparation->tree);

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
